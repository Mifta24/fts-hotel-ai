<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Services\Concierge\StayParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConciergeStayFlowTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.local_llm.base_url' => 'http://llm.test', 'services.local_llm.api_key' => 'k', 'services.local_llm.model' => 'm']);

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia', 'currency' => 'IDR', 'default_locale' => 'en']);
        $this->hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 1, 'is_active' => true]);
        $this->hotel->roomTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2000000, 'max_adults' => 4, 'max_children' => 2, 'is_active' => true]);

        // Every night of the next two months is on sale: 3 Deluxe at 1 juta, 1 Suite at 2 juta.
        foreach ($this->hotel->roomTypes as $roomType) {
            for ($day = 0; $day < 60; $day++) {
                RoomInventory::create(['room_type_id' => $roomType->id, 'stay_date' => now()->addDays($day)->toDateString(), 'total_units' => $roomType->slug === 'deluxe-king' ? 3 : 1, 'booked_units' => 0, 'price' => $roomType->base_price]);
            }
        }
    }

    private function fakeModel(string $reply = 'Here you go.'): void
    {
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => $reply], 'finish_reason' => 'stop']]])]);
    }

    private function say(string $token, string $message)
    {
        return $this->postJson('/demo/concierge/message', ['guest_token' => $token, 'message' => $message])->assertOk();
    }

    public function test_the_parser_reads_the_dates_and_party_from_mr_yoshis_chat(): void
    {
        $today = CarbonImmutable::parse('2026-11-18');

        $stay = StayParser::fromMessages(['nove 25', 'nove 27', 'I said 2adult'], $today);
        $this->assertSame(['check_in' => '2026-11-25', 'check_out' => '2026-11-27', 'adults' => 2, 'children' => null], $stay);

        $stay = StayParser::fromMessages(['check in 11/25 check out 11/27 2adult'], $today);
        $this->assertSame(['2026-11-25', '2026-11-27', 2], [$stay['check_in'], $stay['check_out'], $stay['adults']]);
    }

    public function test_the_parser_handles_indonesian_japanese_and_dates_that_already_passed(): void
    {
        $today = CarbonImmutable::parse('2026-11-18');

        $id = StayParser::fromMessages(['25 november sampai 27 november, 2 dewasa 1 anak'], $today, 'id');
        $this->assertSame(['2026-11-25', '2026-11-27', 2, 1], [$id['check_in'], $id['check_out'], $id['adults'], $id['children']]);

        $ja = StayParser::fromMessages(['11月25日から11月27日、大人2名'], $today, 'ja');
        $this->assertSame(['2026-11-25', '2026-11-27', 2], [$ja['check_in'], $ja['check_out'], $ja['adults']]);

        // A date earlier in the year means next year, never the past.
        $this->assertSame('2027-03-02', StayParser::fromMessages(['march 2'], $today)['check_in']);
        $this->assertNull(StayParser::fromMessages(['hello there'], $today)['check_in']);
        $this->assertNull(StayParser::fromMessages(['feb 31'], $today)['check_in']);
    }

    public function test_asking_what_rooms_exist_shows_the_room_cards_at_once(): void
    {
        $this->fakeModel('We have two room types.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $reply = $this->say($token, 'what kind of room do you have?');

        $reply->assertJsonPath('message.ui_payload.0.type', 'room_results');
        $this->assertCount(2, $reply->json('message.ui_payload.0.rooms'));
    }

    public function test_dates_and_party_given_over_several_messages_show_available_rooms_with_prices(): void
    {
        $this->fakeModel('Two rooms are free for those nights.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');
        $in = now()->addDays(20)->startOfDay()->toImmutable();
        $out = $in->addDays(2);

        $this->say($token, 'check in '.$in->format('m/d'))->assertJsonPath('message.ui_payload', null);
        $this->say($token, 'check out '.$out->format('m/d'))->assertJsonPath('message.ui_payload', null);
        $reply = $this->say($token, 'I said 2adult');

        $reply->assertJsonPath('message.ui_payload.0.type', 'room_results')
            ->assertJsonPath('message.ui_payload.0.check_in', $in->toDateString())
            ->assertJsonPath('message.ui_payload.0.check_out', $out->toDateString());
        $this->assertSame(2, $reply->json('message.ui_payload.0.rooms.0.nights'));
        $this->assertSame(2000000, (int) $reply->json('message.ui_payload.0.rooms.0.total_price'));
    }

    public function test_a_party_too_big_for_the_party_size_only_gets_rooms_that_fit(): void
    {
        $this->fakeModel('Only the suite fits.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');
        $in = now()->addDays(20)->startOfDay()->toImmutable();

        $reply = $this->say($token, "{$in->format('m/d')} to {$in->addDays(2)->format('m/d')}, 4 adults");

        $rooms = $reply->json('message.ui_payload.0.rooms');
        $this->assertSame(['family-suite'], array_column($rooms, 'room_type_slug'));
    }

    public function test_asking_for_the_menu_shows_the_dining_card_without_a_tap(): void
    {
        $this->hotel->knowledgeItems()->create([
            'category' => 'dining', 'title' => 'Breakfast', 'body' => 'Buffet.', 'is_active' => true, 'sort_order' => 0,
            'details' => ['hours' => '06:30–10:30', 'menu' => [['name' => 'Bubur ayam', 'description' => 'With chicken', 'price' => 'Rp 40.000']]],
        ]);
        $this->fakeModel('Here is the menu.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $this->say($token, 'show me the menu')->assertJsonPath('message.ui_payload.0.type', 'dining_menu');
    }

    public function test_the_room_menu_means_the_rooms_not_the_restaurant(): void
    {
        $this->fakeModel('Our rooms.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $this->say($token, 'show the room menu')->assertJsonPath('message.ui_payload.0.type', 'room_results');
    }

    public function test_a_stay_with_no_free_room_still_tells_the_model_so(): void
    {
        $this->fakeModel('Sorry, nothing is free then.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');
        $in = now()->addDays(20)->startOfDay()->toImmutable();

        $reply = $this->say($token, "{$in->format('m/d')} to {$in->addDays(2)->format('m/d')}, 9 adults");

        $reply->assertJsonPath('message.ui_payload', null);
        Http::assertSent(fn (Request $request) => collect($request['messages'])->contains(fn ($m) => ($m['role'] ?? '') === 'tool' && str_contains((string) $m['content'], 'No room type has availability')));
    }

    public function test_the_latest_message_wins_when_the_guest_types_seconds_apart(): void
    {
        $this->fakeModel('Fine.');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'en'])->json('guest_token');

        $this->travelTo(now()->addMinutes(5));
        $this->say($token, 'hello');
        $this->travelTo(now()->addMinutes(5));

        // The first message ("hello") must not be mistaken for the latest one.
        $this->say($token, 'what kind of room do you have?')->assertJsonPath('message.ui_payload.0.type', 'room_results');
    }

    public function test_room_facilities_are_not_mistaken_for_the_room_list(): void
    {
        $this->fakeModel('Which room?');
        $token = $this->postJson('/demo/concierge/start', ['locale' => 'id'])->json('guest_token');

        $this->say($token, 'fasilitas kamar apa saja?')->assertJsonPath('message.ui_payload', null);
    }
}
