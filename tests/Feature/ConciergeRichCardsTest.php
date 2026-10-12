<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\Concierge\HotelConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConciergeRichCardsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $deluxe;

    private Conversation $conversation;

    private CarbonImmutable $checkIn;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.local_llm.base_url' => 'http://llm.test',
            'services.local_llm.api_key' => 'test-key',
            'services.local_llm.model' => 'test-model',
        ]);

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en', 'timezone' => 'Asia/Makassar']);
        $this->deluxe = $this->hotel->roomTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'description' => 'A calm room.', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 1,
            'bed_config' => [['type' => 'king', 'count' => 1]], 'amenities' => ['wifi', 'bathtub'], 'breakfast_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->deluxe->images()->create(['image_url' => 'https://img.test/deluxe.jpg', 'alt_text' => 'Deluxe bedroom', 'sort_order' => 0]);
        $this->hotel->roomTypes()->create([
            'name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2000000, 'max_adults' => 4, 'max_children' => 2, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->conversation = Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
        $this->checkIn = CarbonImmutable::now('Asia/Makassar')->startOfDay()->addDays(5);

        foreach ([0, 1] as $offset) {
            RoomInventory::create(['room_type_id' => $this->deluxe->id, 'stay_date' => $this->checkIn->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }
    }

    private function tools(): HotelConciergeTools
    {
        return new HotelConciergeTools($this->hotel, $this->conversation, 'en');
    }

    public function test_a_recommendation_shows_room_cards_without_asking_for_dates(): void
    {
        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'recommend_rooms', 'arguments' => '{}']]]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'These two suit most guests.'], 'finish_reason' => 'stop']]]),
        ]);

        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $response = $this->postJson('/demo/concierge/message', ['guest_token' => $token, 'message' => 'Recommend a room'])
            ->assertOk()
            ->assertJsonPath('message.ui_payload.0.type', 'room_results')
            ->assertJsonPath('message.ui_payload.0.check_in', null)
            ->assertJsonPath('message.ui_payload.0.rooms.0.room_type_slug', 'deluxe-king')
            ->assertJsonPath('message.ui_payload.0.rooms.0.starting_price', 1000000)
            ->assertJsonPath('message.ui_payload.0.rooms.0.thumbnail_url', 'https://img.test/deluxe.jpg')
            ->assertJsonPath('message.ui_payload.0.rooms.0.amenities', ['wifi', 'bathtub'])
            ->assertJsonPath('message.ui_payload.0.rooms.1.room_type_slug', 'family-suite');

        $this->assertCount(2, $response->json('message.ui_payload.0.rooms'));

        Http::assertSent(function (Request $request) {
            $tool = collect($request['messages'])->firstWhere('role', 'tool');

            return $tool !== null && str_contains($tool['content'], 'Deluxe King') && ! str_contains($tool['content'], 'img.test');
        });
    }

    public function test_a_recommendation_only_offers_rooms_that_fit_the_party(): void
    {
        $result = $this->tools()->dispatch('recommend_rooms', ['adults' => 4]);

        $this->assertSame(['family-suite'], array_column($result['ui']['rooms'], 'room_type_slug'));

        $this->assertNull($this->tools()->dispatch('recommend_rooms', ['adults' => 9])['ui']);
    }

    public function test_the_booking_form_opens_prefilled_and_drops_dates_in_the_past(): void
    {
        $result = $this->tools()->dispatch('open_booking_form', [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn->toDateString(), 'check_out' => $this->checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ]);

        $this->assertSame('booking_form', $result['ui']['type']);
        $this->assertSame('deluxe-king', $result['ui']['room']['room_type_slug']);
        $this->assertSame([
            'check_in' => $this->checkIn->toDateString(), 'check_out' => $this->checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'guest_name' => 'Ayu', 'contact_value' => '+62 811 111 222',
        ], $result['ui']['values']);

        $stale = $this->tools()->dispatch('open_booking_form', ['room_type_slug' => 'deluxe-king', 'check_in' => '2020-01-01']);
        $this->assertSame([], $stale['ui']['values']);

        $this->assertNull($this->tools()->dispatch('open_booking_form', ['room_type_slug' => 'no-such-room'])['ui']);
    }

    public function test_the_ai_will_not_book_a_stay_that_starts_in_the_past(): void
    {
        $result = $this->tools()->dispatch('create_booking_request', [
            'room_type_slug' => 'deluxe-king', 'check_in' => '2020-01-01', 'check_out' => '2020-01-03',
            'adults' => 2, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ]);

        $this->assertNull($result['ui']);
        $this->assertSame(0, Booking::count());
    }

    public function test_the_ai_rejects_an_invalid_phone_but_never_needs_an_email(): void
    {
        $input = [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn->toDateString(), 'check_out' => $this->checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'guest_name' => 'Miftah', 'guest_phone' => '08865',
        ];

        $this->assertNull($this->tools()->dispatch('create_booking_request', $input)['ui']);
        $this->assertSame(0, Booking::count());

        $this->assertNull($this->tools()->dispatch('create_booking_request', [...$input, 'guest_phone' => '081234567890', 'guest_email' => 'not-an-email'])['ui']);

        $booked = $this->tools()->dispatch('create_booking_request', [...$input, 'guest_phone' => '081234567890']);

        $this->assertSame('booking_confirmation', $booked['ui']['type']);
        $this->assertNull(Booking::firstOrFail()->guest_email);
    }

    public function test_availability_cards_carry_the_room_so_they_can_open_the_form(): void
    {
        $result = $this->tools()->dispatch('check_availability', [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn->toDateString(), 'check_out' => $this->checkIn->addDays(2)->toDateString(),
        ]);

        $this->assertSame('deluxe-king', $result['ui']['room']['room_type_slug']);
        $this->assertSame(2000000.0, $result['ui']['quote']['grand_total']);
    }

    public function test_a_booking_made_with_the_in_chat_form_is_kept_in_the_conversation(): void
    {
        $response = $this->postJson('/demo/reservation', [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn->toDateString(), 'check_out' => $this->checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'locale' => 'en', 'guest_name' => 'Ayu', 'contact_type' => 'whatsapp', 'contact_value' => '+62 811 111 222',
            'guest_token' => $this->conversation->guest_token, 'via_chat' => true,
        ])->assertCreated()
            ->assertJsonPath('confirmation.type', 'booking_confirmation')
            ->assertJsonPath('confirmation.booking.total_price', 2000000)
            ->assertJsonPath('confirmation.booking.nights', 2);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $response->json('confirmation.booking.booking_reference'));
        $this->assertSame($this->conversation->id, $booking->conversation_id);

        $saved = $this->conversation->messages()->firstOrFail();
        $this->assertSame(ConversationMessage::ROLE_ASSISTANT, $saved->role);
        $this->assertSame('booking_confirmation', $saved->ui_payload[0]['type']);

        $this->getJson('/demo/concierge/history?guest_token='.$this->conversation->guest_token)
            ->assertOk()
            ->assertJsonPath('messages.0.ui_payload.0.booking.booking_reference', $booking->reference);
    }

    public function test_a_booking_from_the_reservation_page_leaves_the_conversation_alone(): void
    {
        $this->postJson('/demo/reservation', [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn->toDateString(), 'check_out' => $this->checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'locale' => 'en', 'guest_name' => 'Ayu', 'contact_type' => 'whatsapp', 'contact_value' => '+62 811 111 222',
            'guest_token' => $this->conversation->guest_token,
        ])->assertCreated()->assertJsonPath('confirmation', null);

        $this->assertSame(0, $this->conversation->messages()->count());
    }
}
