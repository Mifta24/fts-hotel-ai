<?php

namespace App\Services\Concierge;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\Hotel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What guests ask the concierge, what it could not answer, and whether chats
 * turn into reservations — computed from the conversations the hotel already stores.
 */
class ConciergeAnalytics
{
    private const TOP = 10;

    /**
     * @return array<string, mixed>
     */
    public function report(Hotel $hotel, int $days): array
    {
        $since = CarbonImmutable::now()->subDays($days);

        $conversations = Conversation::where('hotel_id', $hotel->id)->where('created_at', '>=', $since);
        $messages = ConversationMessage::query()
            ->join('conversations', 'conversations.id', '=', 'conversation_messages.conversation_id')
            ->where('conversations.hotel_id', $hotel->id)
            ->where('conversation_messages.created_at', '>=', $since)
            ->select('conversation_messages.*');

        $engaged = (clone $messages)->where('conversation_messages.role', ConversationMessage::ROLE_GUEST)->distinct()->count('conversation_messages.conversation_id');

        $guestMessages = (clone $messages)->where('conversation_messages.role', ConversationMessage::ROLE_GUEST)->get();
        $assistantMessages = (clone $messages)->where('conversation_messages.role', ConversationMessage::ROLE_ASSISTANT)->get();

        $bookings = Booking::where('hotel_id', $hotel->id)->where('created_at', '>=', $since);
        $bookingsTotal = (clone $bookings)->count();
        $bookingsFromChat = (clone $bookings)->whereNotNull('conversation_id')->count();
        $chatsThatBooked = (clone $bookings)->whereNotNull('conversation_id')->distinct()->count('conversation_id');

        $toolCalls = $assistantMessages->flatMap(fn (ConversationMessage $message) => $message->tool_calls ?? []);

        return [
            'days' => $days,
            'conversations' => (clone $conversations)->count(),
            'engaged' => $engaged,
            'guest_messages' => $guestMessages->count(),
            'by_locale' => (clone $conversations)->selectRaw('locale, count(*) as total')->groupBy('locale')->orderByDesc('total')->pluck('total', 'locale')->all(),
            'handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('hotel_id', $hotel->id))->where('created_at', '>=', $since)->count(),
            'handover_reasons' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('hotel_id', $hotel->id))->where('created_at', '>=', $since)
                ->selectRaw('reason, count(*) as total')->groupBy('reason')->orderByDesc('total')->pluck('total', 'reason')->all(),
            'bookings_total' => $bookingsTotal,
            'bookings_from_chat' => $bookingsFromChat,
            'conversion' => $engaged > 0 ? round($chatsThatBooked / $engaged * 100, 1) : null,
            'repeated_questions' => $this->repeatedQuestions($guestMessages),
            'topics' => $this->tally($toolCalls->where('name', 'search_knowledge')->map(fn ($call) => $call['input']['query'] ?? '')),
            'tools' => $this->tally($toolCalls->pluck('name')),
            'gaps' => $this->tally($toolCalls->where('miss', true)->map(fn ($call) => $call['input']['query'] ?? ($call['name'] ?? ''))),
            'unanswered_handovers' => HandoverRequest::whereHas('conversation', fn ($q) => $q->where('hotel_id', $hotel->id))
                ->where('reason', HandoverRequest::REASON_LOW_CONFIDENCE)->where('created_at', '>=', $since)->latest()->take(5)->pluck('summary')->all(),
            'ratings' => [
                'up' => $assistantMessages->where('rating', 1)->count(),
                'down' => $assistantMessages->where('rating', -1)->count(),
            ],
            'disliked' => $this->dislikedReplies($messages),
        ];
    }

    /**
     * Questions guests typed more than once, worded alike (case and trailing punctuation ignored).
     *
     * @param  Collection<int, ConversationMessage>  $guestMessages
     * @return list<array{text: string, count: int}>
     */
    private function repeatedQuestions(Collection $guestMessages): array
    {
        return $guestMessages
            ->map(fn (ConversationMessage $message) => Str::of((string) $message->content)->lower()->squish()->trim(" \t.!?。！？")->toString())
            ->filter(fn (string $text) => mb_strlen($text) >= 4)
            ->countBy()
            ->filter(fn (int $count) => $count >= 2)
            ->sortDesc()
            ->take(self::TOP)
            ->map(fn (int $count, string $text) => ['text' => $text, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return list<array{text: string, count: int}>
     */
    private function tally(Collection $values): array
    {
        return $values
            ->map(fn ($value) => Str::of((string) $value)->lower()->squish()->toString())
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(self::TOP)
            ->map(fn (int $count, string $text) => ['text' => $text, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * The latest replies a guest marked as not helpful, with the question they answered.
     *
     * @return list<array{question: ?string, reply: ?string, at: string}>
     */
    private function dislikedReplies($messages): array
    {
        return (clone $messages)
            ->where('conversation_messages.role', ConversationMessage::ROLE_ASSISTANT)
            ->where('conversation_messages.rating', -1)
            ->latest('conversation_messages.id')
            ->take(10)
            ->get()
            ->map(fn (ConversationMessage $reply) => [
                'question' => ConversationMessage::where('conversation_id', $reply->conversation_id)
                    ->where('role', ConversationMessage::ROLE_GUEST)->where('id', '<', $reply->id)->latest('id')->value('content'),
                'reply' => $reply->content,
                'at' => $reply->created_at->diffForHumans(),
            ])
            ->all();
    }
}
