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

    public function test_showing_facilities_returns_photo_cards_only_for_entries_with_a_photo(): void
    {
        $this->hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Swimming Pool', 'body' => 'Open 7am to 9pm.', 'tags' => ['pool'], 'image_url' => 'https://img.test/pool.jpg', 'is_active' => true, 'sort_order' => 0]);
        $this->hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool Bar', 'body' => 'Drinks by the pool.', 'tags' => ['pool'], 'is_active' => true, 'sort_order' => 1]);
        $this->hotel->knowledgeItems()->create(['category' => 'policies', 'title' => 'Pool rules', 'body' => 'No diving.', 'image_url' => 'https://img.test/rules.jpg', 'is_active' => true, 'sort_order' => 2]);

        $result = $this->tools()->dispatch('show_facility_photos', ['query' => 'pool']);

        $this->assertSame('facility_results', $result['ui']['type']);
        $this->assertCount(1, $result['ui']['facilities']);
        $this->assertSame('Swimming Pool', $result['ui']['facilities'][0]['title']);
        $this->assertSame('https://img.test/pool.jpg', $result['ui']['facilities'][0]['image_url']);
        $this->assertStringNotContainsString('img.test', $result['text']);
    }

    public function test_showing_facilities_without_a_matching_photo_shows_no_cards(): void
    {
        $result = $this->tools()->dispatch('show_facility_photos', ['query' => 'helipad']);

        $this->assertNull($result['ui']);
    }

    public function test_a_facility_card_carries_its_whole_gallery_and_hours(): void
    {
        $this->hotel->knowledgeItems()->create([
            'category' => 'facilities', 'title' => 'Swimming Pool', 'body' => 'Open daily.', 'image_url' => 'https://img.test/pool.jpg',
            'images' => ['https://img.test/pool.jpg', 'https://img.test/pool-night.jpg'], 'details' => ['hours' => '07:00–19:00', 'menu' => []],
            'is_active' => true, 'sort_order' => 0,
        ]);

        $facility = $this->tools()->dispatch('show_facility_photos', [])['ui']['facilities'][0];

        $this->assertSame(['https://img.test/pool.jpg', 'https://img.test/pool-night.jpg'], $facility['images']);
        $this->assertSame('07:00–19:00', $facility['hours']);
    }

    public function test_the_location_card_has_map_links_and_how_to_get_there(): void
    {
        $this->hotel->update(['address' => 'Jl. Pantai 8', 'city' => 'Nusa Dua', 'country' => 'Indonesia', 'latitude' => -8.8008, 'longitude' => 115.2317]);
        $this->hotel->knowledgeItems()->create(['category' => 'transport', 'title' => 'Airport transfer', 'body' => '30 minutes from the airport.', 'is_active' => true, 'sort_order' => 0]);

        $result = $this->tools()->dispatch('show_location', []);

        $this->assertSame('location', $result['ui']['type']);
        $this->assertSame('Jl. Pantai 8, Nusa Dua, Indonesia', $result['ui']['location']['address']);
        $this->assertStringContainsString('destination=-8.8008%2C115.2317', $result['ui']['location']['directions_url']);
        $this->assertSame('Airport transfer', $result['ui']['location']['getting_there'][0]['title']);
    }

    public function test_the_location_card_is_skipped_when_the_hotel_has_no_address_or_point(): void
    {
        $this->hotel->update(['address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null]);

        $this->assertNull($this->tools()->dispatch('show_location', [])['ui']);
    }

    public function test_room_photos_can_be_filtered_by_what_the_guest_wants_to_see(): void
    {
        $this->deluxe->images()->create(['image_url' => 'https://img.test/deluxe-bath.jpg', 'tags' => ['bathroom'], 'alt_text' => 'Deluxe bathroom', 'sort_order' => 1]);

        $result = $this->tools()->dispatch('show_room_photos', ['image_tag' => 'Bathroom']);

        $this->assertSame('room_photos', $result['ui']['type']);
        $this->assertCount(1, $result['ui']['photos']);
        $this->assertSame('https://img.test/deluxe-bath.jpg', $result['ui']['photos'][0]['url']);
        $this->assertSame('deluxe-king', $result['ui']['photos'][0]['room_type_slug']);
        $this->assertStringNotContainsString('img.test', $result['text']);
        $this->assertNull($this->tools()->dispatch('show_room_photos', ['image_tag' => 'helipad'])['ui']);
    }

    public function test_room_photos_find_a_room_by_a_loose_name_instead_of_its_exact_slug(): void
    {
        $suite = $this->hotel->roomTypes()->where('slug', 'family-suite')->first();
        $suite->images()->create(['image_url' => 'https://img.test/suite.jpg', 'alt_text' => 'Suite living area', 'sort_order' => 0]);

        foreach (['suite', 'suite room', 'Family Suite', 'family-suite'] as $term) {
            $result = $this->tools()->dispatch('show_room_photos', ['room_type_slug' => $term]);

            $this->assertSame('room_photos', $result['ui']['type'], $term);
            $this->assertSame(['family-suite'], array_values(array_unique(array_column($result['ui']['photos'], 'room_type_slug'))), $term);
        }

        $unknown = $this->tools()->dispatch('show_room_photos', ['room_type_slug' => 'castle']);

        $this->assertNull($unknown['ui']);
        $this->assertStringContainsString('family-suite', $unknown['text']);
    }

    public function test_the_menu_card_lists_the_dishes_and_hours_the_hotel_entered(): void
    {
        $this->hotel->knowledgeItems()->create([
            'category' => 'dining', 'title' => 'Breakfast', 'body' => 'Buffet at Samudra.', 'image_url' => 'https://img.test/breakfast.jpg',
            'details' => ['hours' => '06:30–10:30', 'menu' => [['name' => 'Bubur ayam', 'description' => 'With chicken', 'price' => 'Rp 40.000'], ['name' => '']]],
            'is_active' => true, 'sort_order' => 0,
        ]);

        $entry = $this->tools()->dispatch('show_menu', [])['ui']['entries'][0];

        $this->assertSame('06:30–10:30', $entry['hours']);
        $this->assertSame([['name' => 'Bubur ayam', 'description' => 'With chicken', 'price' => 'Rp 40.000']], $entry['menu']);
        $this->assertSame(['https://img.test/breakfast.jpg'], $entry['images']);
    }

    public function test_quick_replies_after_the_new_cards_point_to_the_next_thing_to_see(): void
    {
        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'show_location', 'arguments' => '{}']]]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'Here is where we are.'], 'finish_reason' => 'stop']]]),
        ]);
        $this->hotel->update(['address' => 'Jl. Pantai 8', 'latitude' => -8.8, 'longitude' => 115.2]);

        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $this->postJson('/demo/concierge/message', ['guest_token' => $token, 'message' => 'Where is the hotel?'])
            ->assertOk()
            ->assertJsonPath('message.ui_payload.0.type', 'location')
            ->assertJsonPath('message.quick_replies', ['facilities', 'rooms', 'tour']);
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

    public function test_a_reply_that_only_promises_an_action_is_sent_back_to_call_the_tool(): void
    {
        $call = ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'open_booking_form', 'arguments' => json_encode(['room_type_slug' => 'deluxe-king'])]];

        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => "I'll proceed with creating a reservation for Miftah."], 'finish_reason' => 'stop']]])
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [$call]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'Please check the details and tap confirm.'], 'finish_reason' => 'stop']]]),
        ]);

        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $this->postJson('/demo/concierge/message', ['guest_token' => $token, 'message' => 'miftah, 08123456789'])
            ->assertOk()
            ->assertJsonPath('message.ui_payload.0.type', 'booking_form')
            ->assertJsonPath('message.content', 'Please check the details and tap confirm.');

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => collect($request['messages'])->contains(fn ($m) => str_contains((string) ($m['content'] ?? ''), 'called no tool')));
    }

    public function test_the_stay_picker_opens_prefilled_and_ignores_dates_in_the_past(): void
    {
        $result = $this->tools()->dispatch('ask_stay_details', [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn->toDateString(), 'adults' => 3, 'check_out' => '2020-01-03',
        ]);

        $this->assertSame('stay_picker', $result['ui']['type']);
        $this->assertSame('deluxe-king', $result['ui']['room']['room_type_slug']);
        $this->assertSame(['check_in' => $this->checkIn->toDateString(), 'adults' => 3], $result['ui']['values']);

        $open = $this->tools()->dispatch('ask_stay_details', []);
        $this->assertNull($open['ui']['room']);
        $this->assertSame([], $open['ui']['values']);
    }

    public function test_the_concierge_can_start_the_guided_tour(): void
    {
        $this->assertSame('tour_start', $this->tools()->dispatch('start_tour', [])['ui']['type']);

        $names = collect(HotelConciergeTools::definitions())->pluck('function.name');
        $this->assertTrue($names->contains('ask_stay_details') && $names->contains('start_tour'));
    }

    public function test_replies_offer_tappable_answers_that_fit_what_was_shown(): void
    {
        $token = $this->conversation->guest_token;
        $reply = fn (array $payload, ?string $content = 'Here you go.') => $this->conversation->messages()->create(['role' => ConversationMessage::ROLE_ASSISTANT, 'content' => $content, 'ui_payload' => $payload ?: null]);
        $history = fn () => collect($this->getJson('/demo/concierge/history?guest_token='.$token)->assertOk()->json('messages'))->last()['quick_replies'];

        $reply([['type' => 'room_results', 'check_in' => null, 'check_out' => null, 'rooms' => []]]);
        $this->assertSame(['pick_dates', 'cheapest', 'family', 'view'], $history());

        $reply([['type' => 'room_results', 'check_in' => '2030-01-01', 'check_out' => '2030-01-03', 'rooms' => []]]);
        $this->assertSame(['cheapest', 'family', 'view'], $history());

        $reply([['type' => 'booking_form', 'room' => [], 'values' => []]]);
        $this->assertSame([], $history());

        $reply([['type' => 'booking_confirmation', 'booking' => []]]);
        $this->assertSame(['facilities', 'policies', 'tour'], $history());

        $reply([], 'Happy to help!');
        $this->assertSame(['rooms', 'facilities', 'location', 'policies', 'tour'], $history());

        $reply([['type' => 'handover']], null);
        $this->assertSame([], $history());
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
