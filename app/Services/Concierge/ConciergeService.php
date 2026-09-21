<?php

namespace App\Services\Concierge;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Hotel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates one guest turn against a self-hosted, OpenAI-compatible chat
 * endpoint (LM Studio / Ollama over Tailscale) — runs the tool-use loop
 * against a single hotel's HotelConciergeTools, persists the conversation,
 * and returns the assistant message (with any UI payload to render).
 *
 * The local model is a "thinking" model that reasons at length before it
 * emits a tool call, so max_tokens must stay generous (see MAX_TOKENS) —
 * too small a budget truncates it mid-thought and it never calls the tool.
 */
class ConciergeService
{
    private const MAX_TOOL_ITERATIONS = 6;

    private const MAX_TOKENS = 4096;

    private const REQUEST_TIMEOUT_SECONDS = 120;

    public function startConversation(Hotel $hotel, string $locale = 'id'): Conversation
    {
        return Conversation::create([
            'hotel_id' => $hotel->id,
            'guest_token' => (string) Str::uuid(),
            'locale' => $locale,
            'status' => Conversation::STATUS_ACTIVE,
            'last_message_at' => now(),
        ]);
    }

    /**
     * Remembers where in the UI the guest is, so the concierge can answer for
     * "this room" or the reservation they are filling in. Only values that
     * exist for this hotel are kept; nothing personal is stored here.
     *
     * @param  array{scene?: ?string, selected_room?: ?string, selected_facility?: ?int, reservation?: ?array<string, mixed>}  $context
     */
    public function rememberContext(Hotel $hotel, Conversation $conversation, array $context): void
    {
        $updates = [];

        if (in_array($context['scene'] ?? null, Conversation::SCENES, true)) {
            $updates['current_scene'] = $context['scene'];
        }

        if (filled($context['selected_room'] ?? null)) {
            $roomType = $hotel->roomTypes()->where('is_active', true)->where('slug', $context['selected_room'])->first();

            if ($roomType) {
                $updates['selected_room_type_id'] = $roomType->id;
            }
        }

        if (filled($context['selected_facility'] ?? null)) {
            $facility = $hotel->knowledgeItems()->where('is_active', true)->whereKey($context['selected_facility'])->first();

            if ($facility) {
                $updates['selected_facility_id'] = $facility->id;
            }
        }

        if (array_key_exists('reservation', $context)) {
            $updates['reservation_state'] = $context['reservation'] ?: null;
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }
    }

    public function reply(Hotel $hotel, Conversation $conversation, string $guestMessage): ConversationMessage
    {
        $guestRecord = $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_GUEST,
            'content' => $guestMessage,
        ]);

        if ($conversation->isHandedOver()) {
            return $conversation->messages()->create([
                'role' => ConversationMessage::ROLE_SYSTEM,
                'content' => 'This conversation is with hotel staff now. A team member will respond shortly.',
            ]);
        }

        try {
            return $this->answer($hotel, $conversation);
        } catch (\Throwable $e) {
            // The guest keeps the text on screen and can retry; leaving it here would duplicate it.
            $guestRecord->delete();

            throw $e;
        }
    }

    private function answer(Hotel $hotel, Conversation $conversation): ConversationMessage
    {
        $tools = new HotelConciergeTools($hotel, $conversation, $conversation->locale);
        $definitions = HotelConciergeTools::definitions();

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($hotel, $conversation)],
            ...$this->buildHistory($conversation),
        ];

        $toolLog = [];
        $uiPayloads = [];

        $message = $this->chatCompletion($messages, $definitions);

        $iterations = 0;
        while (! empty($message['tool_calls']) && $iterations < self::MAX_TOOL_ITERATIONS) {
            $iterations++;

            $messages[] = [
                'role' => 'assistant',
                'content' => $message['content'] ?? '',
                'tool_calls' => $message['tool_calls'],
            ];

            foreach ($message['tool_calls'] as $call) {
                $name = $call['function']['name'] ?? '';
                $input = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

                $result = $tools->dispatch($name, $input);

                $toolLog[] = ['name' => $name, 'input' => $input];
                if ($result['ui'] !== null) {
                    $uiPayloads[] = $result['ui'];
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? '',
                    'content' => $result['text'],
                ];
            }

            $message = $this->chatCompletion($messages, $definitions);
        }

        $text = trim((string) ($message['content'] ?? ''));

        // A tool (e.g. request_human_handover) may have changed the
        // conversation's status/summary directly in the DB this turn.
        $conversation->refresh();
        $conversation->update(['last_message_at' => now()]);

        return $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_ASSISTANT,
            'content' => $text !== '' ? $text : null,
            'ui_payload' => $uiPayloads !== [] ? $uiPayloads : null,
            'tool_calls' => $toolLog !== [] ? $toolLog : null,
        ]);
    }

    /**
     * One call to the local model's OpenAI-compatible /v1/chat/completions.
     *
     * @return array{content: ?string, tool_calls: ?array}
     */
    private function chatCompletion(array $messages, array $tools): array
    {
        $response = Http::withToken(config('services.local_llm.api_key'))
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->post(rtrim(config('services.local_llm.base_url'), '/').'/v1/chat/completions', [
                'model' => config('services.local_llm.model'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.3,
                'max_tokens' => self::MAX_TOKENS,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Local LLM request failed: HTTP {$response->status()} — {$response->body()}");
        }

        $message = $response->json('choices.0.message', []);
        $finishReason = $response->json('choices.0.finish_reason');

        if ($finishReason === 'length' && empty($message['tool_calls'])) {
            throw new RuntimeException('Local LLM ran out of tokens mid-thought before it could respond. Increase MAX_TOKENS.');
        }

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $message['tool_calls'] ?? null,
        ];
    }

    private function buildHistory(Conversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_GUEST, ConversationMessage::ROLE_ASSISTANT])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationMessage $message) => [
                'role' => $message->role === ConversationMessage::ROLE_GUEST ? 'user' : 'assistant',
                'content' => (string) $message->content,
            ])
            ->filter(fn (array $m) => $m['content'] !== '')
            ->values()
            ->all();
    }

    private function buildSystemPrompt(Hotel $hotel, Conversation $conversation): string
    {
        $locale = $conversation->locale;
        $localeNames = ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語 (Japanese)'];
        $localeName = $localeNames[$locale] ?? "the guest's language";
        $today = now($hotel->timezone)->toDateString();

        return <<<PROMPT
        You are the AI Concierge for {$hotel->name}, a hotel in {$hotel->city}, {$hotel->country}. You work inside the hotel's own website, not a generic chat widget — guests should feel they are talking to a knowledgeable hotel staff member who can also pull up rooms, photos and prices for them.

        Hard rules, never break these:
        1. Reply in {$localeName} unless the guest clearly switches language, then follow them.
        2. Never state a hotel fact (policies, facilities, hours, dining, transport) from memory. Always call search_knowledge first. If nothing relevant comes back, say you will confirm with the team, or call request_human_handover — never guess.
        3. Never state a room price or availability from memory. Always call search_rooms or check_availability. Prices and availability change constantly and only those tools see the real data.
        4. When you call search_rooms, get_room_detail, or check_availability, the matching rooms/photos are already rendered on screen for the guest as you respond — write your reply as a short, natural comment on what they're now looking at, not a repeated listing of every field.
        5. Before calling create_booking_request you must have: room, exact dates, party size, guest name, and phone. Confirm any missing ones with the guest first.
        6. Call request_human_handover for: special requests, complaints, group bookings, negotiated rates, unusual cancellations, payment problems, or anything you cannot answer confidently. Write the summary as if a colleague who has not read this conversation needs to act on it immediately.
        7. Be warm, concise, and professional — like an experienced hotel concierge, not a generic assistant. Keep replies short; let the rendered room cards carry the detail.

        Currency for all prices: {$hotel->currency}. Today's date: {$today}.

        {$this->buildUiContext($conversation)}
        PROMPT;
    }

    /**
     * Tells the model what the guest is looking at, per the scene-aware rules
     * of the product spec. Room names come from the database, never the guest.
     */
    private function buildUiContext(Conversation $conversation): string
    {
        $scene = in_array($conversation->current_scene, Conversation::SCENES, true) ? $conversation->current_scene : 'reception';
        $room = $conversation->selectedRoomType;

        $guidance = match ($scene) {
            'lobby', 'reception' => 'Help with rooms, facilities, hotel information, reservations or reaching staff. Do not repeat the welcome greeting.',
            'rooms' => 'The guest is browsing the room list. Help them compare rooms and pick one; use search_rooms when they give dates and party size.',
            'room_detail' => 'The guest is looking at the selected room on screen. Treat "this room" as that room, answer questions about it, and suggest a reservation when it fits. Use get_room_detail / check_availability with its slug; never quote a price from memory.',
            'facilities' => 'The guest is looking at the list of hotel facilities. Answer with search_knowledge and keep the focus on facilities.',
            'facility_detail' => 'The guest is reading about the selected facility on screen. Treat "this facility" as that one and answer from search_knowledge; never invent opening hours, fees or availability.',
            'reservation' => 'The guest is filling in the reservation form on screen. Collect only what is still missing, validate dates and party size, and summarise before any submission. Never ask for card details.',
            'handover' => 'The guest is on the staff contact screen. Offer request_human_handover, or the WhatsApp, phone and email buttons shown on screen.',
        };

        $lines = [
            'CURRENT UI CONTEXT',
            "Current scene: {$scene}",
            'Selected room: '.($room ? "{$room->name} (slug: {$room->slug})" : 'none'),
            'Selected facility: '.($conversation->selectedFacility?->title ?? 'none'),
        ];

        $draft = $conversation->reservation_state;

        if (is_array($draft) && $draft !== []) {
            $lines[] = 'Reservation draft on screen: '.collect($draft)->map(fn ($value, $key) => "{$key}={$value}")->implode(', ');
        }

        $lines[] = "Scene guidance: {$guidance}";

        return implode("\n", $lines);
    }
}
