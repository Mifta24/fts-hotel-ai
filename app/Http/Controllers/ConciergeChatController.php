<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Hotel;
use App\Services\Concierge\ConciergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConciergeChatController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly ConciergeService $concierge) {}

    public function start(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);

        $locale = in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $hotel->default_locale;

        $conversation = $this->concierge->startConversation($hotel, $locale);

        return response()->json([
            'guest_token' => $conversation->guest_token,
            'locale' => $conversation->locale,
        ]);
    }

    public function message(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);

        $data = $request->validate([
            'guest_token' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $this->findConversation($hotel, $data['guest_token']);

        try {
            $assistantMessage = $this->concierge->reply($hotel, $conversation, $data['message']);
        } catch (\Throwable $e) {
            Log::error('Concierge reply failed', ['hotel_id' => $hotel->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'concierge_unavailable',
                'message' => 'The AI Concierge is temporarily unavailable. Please try again in a moment.',
            ], 503);
        }

        return response()->json([
            'message' => $this->formatMessage($assistantMessage),
            'status' => $conversation->fresh()->status,
        ]);
    }

    public function history(Request $request, string $hotelSlug): JsonResponse
    {
        $hotel = $this->publishedHotel($hotelSlug);

        $data = $request->validate(['guest_token' => ['required', 'uuid']]);

        $conversation = $this->findConversation($hotel, $data['guest_token']);

        $messages = $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_GUEST, ConversationMessage::ROLE_ASSISTANT, ConversationMessage::ROLE_SYSTEM])
            ->orderBy('created_at')
            ->get()
            ->map(fn (ConversationMessage $m) => $this->formatMessage($m))
            ->values();

        return response()->json([
            'messages' => $messages,
            'status' => $conversation->status,
        ]);
    }

    private function publishedHotel(string $hotelSlug): Hotel
    {
        $hotel = Hotel::where('slug', $hotelSlug)->first();

        abort_if(! $hotel || ! $hotel->isPublished(), 404);

        return $hotel;
    }

    private function findConversation(Hotel $hotel, string $guestToken): Conversation
    {
        $conversation = Conversation::where('hotel_id', $hotel->id)
            ->where('guest_token', $guestToken)
            ->first();

        if (! $conversation) {
            throw ValidationException::withMessages([
                'guest_token' => 'This conversation no longer exists. Please start a new one.',
            ]);
        }

        return $conversation;
    }

    private function formatMessage(ConversationMessage $message): array
    {
        return [
            'role' => $message->role,
            'content' => $message->content,
            'ui_payload' => $message->ui_payload,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
