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

    public function reply(Hotel $hotel, Conversation $conversation, string $guestMessage): ConversationMessage
    {
        $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_GUEST,
            'content' => $guestMessage,
        ]);

        if ($conversation->isHandedOver()) {
            return $conversation->messages()->create([
                'role' => ConversationMessage::ROLE_SYSTEM,
                'content' => 'This conversation is with hotel staff now. A team member will respond shortly.',
            ]);
        }

        $tools = new HotelConciergeTools($hotel, $conversation, $conversation->locale);
        $definitions = HotelConciergeTools::definitions();

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($hotel, $conversation->locale)],
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

    private function buildSystemPrompt(Hotel $hotel, string $locale): string
    {
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
        PROMPT;
    }
}
