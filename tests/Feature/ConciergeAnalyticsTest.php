<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConciergeAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
    }

    private function chat(string $locale, array $guestTexts, array $toolCalls = []): Conversation
    {
        $conversation = Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => $locale]);

        foreach ($guestTexts as $text) {
            $conversation->messages()->create(['role' => ConversationMessage::ROLE_GUEST, 'content' => $text]);
            $conversation->messages()->create(['role' => ConversationMessage::ROLE_ASSISTANT, 'content' => 'Answer', 'tool_calls' => $toolCalls ?: null]);
        }

        return $conversation;
    }

    public function test_the_report_counts_chats_languages_gaps_conversion_and_ratings(): void
    {
        $room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $booked = $this->chat('en', ['Is there a pool?', 'Is there a pool?!']);
        $this->chat('id', ['Ada gym?'], [['name' => 'search_knowledge', 'input' => ['query' => 'Gym'], 'miss' => true]]);
        $this->chat('id', ['Ada Gym?'], [['name' => 'search_knowledge', 'input' => ['query' => 'gym'], 'miss' => true]]);
        Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'ja']);

        Booking::create([
            'reference' => 'BK-AN1', 'hotel_id' => $this->hotel->id, 'room_type_id' => $room->id, 'conversation_id' => $booked->id, 'guest_name' => 'Ayu', 'room_count' => 1,
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
        Booking::create([
            'reference' => 'BK-AN2', 'hotel_id' => $this->hotel->id, 'room_type_id' => $room->id, 'guest_name' => 'Walk-in', 'room_count' => 1,
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
        HandoverRequest::create(['conversation_id' => $booked->id, 'reason' => 'low_confidence', 'summary' => 'Asked about helipad']);
        $booked->messages()->where('role', 'assistant')->first()->update(['rating' => -1]);

        $owner = User::factory()->create();
        $this->hotel->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Chats started')
            ->assertSee('3 with a guest message')
            ->assertSee('33.3%')
            ->assertSee('1 of 2 bookings came from chat')
            ->assertSee('Indonesian')
            ->assertSee('is there a pool')
            ->assertSee('gym')
            ->assertSee('2×')
            ->assertSee('Asked about helipad');
    }

    public function test_a_guest_can_rate_and_unrate_a_reply_of_their_own_chat_only(): void
    {
        $chat = $this->chat('en', ['Hello']);
        $reply = $chat->messages()->where('role', 'assistant')->firstOrFail();
        $other = $this->chat('en', ['Hi']);

        $this->postJson('/demo/concierge/feedback', ['guest_token' => $chat->guest_token, 'message_id' => $reply->id, 'rating' => 1])->assertOk()->assertJsonPath('rating', 1);
        $this->assertSame(1, $reply->fresh()->rating);

        $this->postJson('/demo/concierge/feedback', ['guest_token' => $chat->guest_token, 'message_id' => $reply->id, 'rating' => 0])->assertOk();
        $this->assertNull($reply->fresh()->rating);

        $this->postJson('/demo/concierge/feedback', ['guest_token' => $other->guest_token, 'message_id' => $reply->id, 'rating' => -1])->assertNotFound();
        $this->postJson('/demo/concierge/feedback', ['guest_token' => $chat->guest_token, 'message_id' => $reply->id, 'rating' => 5])->assertUnprocessable();

        $guestMessage = $chat->messages()->where('role', 'guest')->firstOrFail();
        $this->postJson('/demo/concierge/feedback', ['guest_token' => $chat->guest_token, 'message_id' => $guestMessage->id, 'rating' => 1])->assertNotFound();

        $this->getJson('/demo/concierge/history?guest_token='.$chat->guest_token)->assertJsonPath('messages.1.id', $reply->id);
    }

    public function test_front_desk_sees_analytics_but_plain_staff_do_not(): void
    {
        $frontDesk = User::factory()->create();
        $staff = User::factory()->create();
        $this->hotel->users()->attach($frontDesk->id, ['role' => 'front_desk', 'status' => 'active']);
        $this->hotel->users()->attach($staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($frontDesk)->get(route('admin.analytics.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.analytics.index'))->assertForbidden();
    }
}
