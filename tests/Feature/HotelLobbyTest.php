<?php

namespace Tests\Feature;

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HotelLobbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_screen_links_to_the_only_published_hotel(): void
    {
        Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        Hotel::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('FTS Hotel AI')
            ->assertSee('Enter Demo')
            ->assertSee('href="'.route('hotel.show', ['hotelSlug' => 'demo', 'lang' => 'en']).'#rooms"', false)
            ->assertDontSee('Draft');
    }

    public function test_opening_screen_lists_every_published_hotel_when_there_are_several(): void
    {
        Hotel::create(['name' => 'First Hotel', 'slug' => 'first', 'public_status' => 'published']);
        Hotel::create(['name' => 'Second Hotel', 'slug' => 'second', 'public_status' => 'published']);
        Hotel::create(['name' => 'Hidden Hotel', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Hotel')->assertSee('Second Hotel')->assertDontSee('Hidden Hotel');
    }

    public function test_opening_screen_explains_when_no_hotel_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The virtual lobby is being prepared');
    }

    public function test_lobby_shows_only_active_facilities_for_the_current_hotel(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true]);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);
        $other = Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Other pool', 'body' => 'Other hotel.', 'is_active' => true]);

        $this->get('/demo')->assertOk()->assertSee('Garden pool')->assertDontSee('Hidden spa')->assertDontSee('Other pool');
        $this->get('/demo?lang=en')->assertOk()->assertSee('Welcome to your')->assertSee('virtual lobby');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('バーチャルロビー');
    }

    public function test_unpublished_lobby_is_not_accessible(): void
    {
        Hotel::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }

    public function test_the_rooms_page_lists_only_active_rooms_of_the_current_hotel(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $hotel->roomTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_adults' => 2, 'max_children' => 1,
            'bed_config' => [['type' => 'king', 'count' => 1]], 'view_type' => 'garden', 'amenities' => ['wifi', 'balcony'],
            'breakfast_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'King bed', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Bathroom', 'sort_order' => 1]);
        $hotel->roomTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 1800000, 'max_adults' => 3, 'max_children' => 2, 'is_active' => true, 'sort_order' => 1]);
        $hotel->roomTypes()->create(['name' => 'Retired Room', 'slug' => 'retired-room', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => false]);
        $other = Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->roomTypes()->create(['name' => 'Foreign Room', 'slug' => 'foreign-room', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => true]);

        $this->get('/demo/rooms?lang=en')
            ->assertOk()
            ->assertSee('Deluxe King')
            ->assertSee('Family Suite')
            // the scene itself is the view now: the concierge greets over the
            // backdrop and the index on the right carries the rooms
            ->assertDontSee('data-room-card', false)
            ->assertSee('narrator-on-stage', false)
            ->assertSee('class="room-nav-thumb"', false)
            ->assertSee('href="'.route('hotel.room', ['hotelSlug' => 'demo', 'roomSlug' => 'deluxe-king', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Room')
            ->assertDontSee('Foreign Room');
    }

    public function test_a_room_page_shows_its_details_gallery_and_neighbouring_rooms(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $hotel->roomTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'size_sqm' => 32, 'max_adults' => 2, 'max_children' => 1,
            'bed_config' => [['type' => 'king', 'count' => 1]], 'view_type' => 'garden', 'amenities' => ['wifi', 'balcony'],
            'breakfast_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'King bed', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Bathroom', 'sort_order' => 1]);
        $hotel->roomTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 1800000, 'max_adults' => 3, 'max_children' => 2, 'is_active' => true, 'sort_order' => 1]);

        $suiteUrl = route('hotel.room', ['hotelSlug' => 'demo', 'roomSlug' => 'family-suite', 'lang' => 'en']);

        $this->get('/demo/rooms/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('Room 1 / 2')
            ->assertSee('King bed')
            ->assertSee('Garden view')
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and rates are confirmed by hotel staff.')
            // both neighbours wrap around to the only other room
            ->assertSee('rel="prev"', false)
            ->assertSee($suiteUrl, false);

        $this->get('/demo/rooms/retired-or-unknown')->assertNotFound();
        $this->get('/demo/rooms/deluxe-king?lang=id')->assertOk()->assertSee('Pemandangan taman');
    }

    public function test_rooms_pages_follow_the_hotel_publication_and_room_state(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $hotel->roomTypes()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => false]);
        Hotel::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/rooms?lang=en')->assertOk()->assertSee('Room information will be available soon.');
        $this->get('/demo/rooms/hidden')->assertNotFound();
        $this->get('/draft/rooms')->assertNotFound();
    }

    public function test_the_lobby_walks_the_guest_to_the_rooms_page(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('hotel.rooms', ['hotelSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Mari, saya antar ke kamar-kamar kami."', false)
            ->assertDontSee('data-lobby-panel="rooms"', false);

        $this->get('/demo/rooms?lang=id')
            ->assertOk()
            ->assertSee('data-tour-line="Mari saya antar kembali ke lobi."', false)
            ->assertSee('data-scene="rooms"', false);
    }

    public function test_reservation_wizard_and_staff_channels_render_from_hotel_data(): void
    {
        $hotel = Hotel::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'whatsapp' => '+62 812-0000-1111',
            'phone' => '+62 361 000', 'email' => 'front@demo.test',
        ]);
        $hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_adults' => 2, 'max_children' => 1, 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-step="5"', false)
            ->assertSee('value="deluxe-king"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee(route('reservation.store', 'demo'), false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:front@demo.test', false);

        $this->get('/demo?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/rooms/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('hotel.show', ['hotelSlug' => 'demo', 'lang' => 'en']).'#reservation/deluxe-king"', false);
    }

    public function test_facility_scenes_link_each_active_facility_with_previous_and_next(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $pool = $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open 07:00 to 20:00.', 'is_active' => true, 'sort_order' => 0]);
        $gym = $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-facility-scene="'.$pool->id.'"', false)
            ->assertSee('data-facility-scene="'.$gym->id.'"', false)
            ->assertDontSee('data-facility-scene="'.$hidden->id.'"', false)
            ->assertSee('href="#facility/'.$gym->id.'"', false)
            ->assertSee('Ask about this facility');
    }

    public function test_hotel_information_panel_lists_only_approved_about_policy_and_faq_entries(): void
    {
        $hotel = Hotel::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'address' => 'Jl. Pantai 8', 'city' => 'Bali', 'country' => 'Indonesia',
            'check_in_time' => '14:00', 'check_out_time' => '12:00', 'description' => 'A beachfront resort.', 'latitude' => -8.8, 'longitude' => 115.23,
        ]);
        $hotel->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free until 48 hours before arrival.', 'is_active' => true]);
        $hotel->knowledgeItems()->create(['category' => 'faq', 'title' => 'Is parking free?', 'body' => 'Yes, for guests.', 'is_active' => true]);
        $hotel->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Belongs to facilities.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-lobby-panel="info"', false)
            ->assertSee('href="#info"', false)
            ->assertSee('Free until 48 hours before arrival.')
            ->assertSee('Is parking free?')
            ->assertSee('14:00 / 12:00')
            ->assertSee('https://www.google.com/maps?q=-8.8000000,115.2300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_lobby_shows_a_localised_loader_and_the_opening_screen_too(): void
    {
        Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=id')->assertOk()->assertSee('data-stage-loader', false)->assertSee('Menyiapkan pengalaman hotel Anda');
        $this->get('/?lang=en')->assertOk()->assertSee('data-stage-exit', false)->assertSee('Preparing your hotel experience');
    }

    public function test_the_concierge_introduces_rooms_using_only_stored_hotel_data(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $hotel->roomTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'description' => 'A calm room with a garden outlook', 'base_price' => 950000, 'size_sqm' => 32,
            'max_adults' => 2, 'max_children' => 1, 'view_type' => 'garden', 'breakfast_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $hotel->roomTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2200000, 'max_adults' => 2, 'max_children' => 2, 'breakfast_included' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->get('/demo/rooms?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('We have 2 room types, from IDR 950.000 per night.');

        $this->get('/demo/rooms/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('Deluxe King. A calm room with a garden outlook. It offers 32 m² for up to 3 guests. Garden view. Breakfast is included. Rates start from IDR 950.000 per night.');

        $this->get('/demo/rooms/family-suite?lang=en')
            ->assertOk()
            ->assertSee('Family Suite. It welcomes up to 4 guests. Rates start from IDR 2.200.000 per night. Final availability and rates are confirmed by our team.');

        $this->get('/demo/rooms?lang=id')->assertOk()->assertSee('Ada 2 tipe kamar, mulai dari IDR 950.000 per malam.');
    }

    public function test_no_rooms_narration_is_shown_when_the_hotel_has_no_rooms(): void
    {
        Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/rooms?lang=en')->assertOk()->assertDontSee('data-key="rooms"', false)->assertDontSee('Here are our rooms');
    }

    public function test_the_concierge_introduces_facilities_and_hotel_information_from_stored_data(): void
    {
        $hotel = Hotel::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia',
            'check_in_time' => '14:00', 'check_out_time' => '12:00',
        ]);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => "Open 07:00 to 20:00.\nTowels included.", 'is_active' => true, 'sort_order' => 0]);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('We have 2 facilities and services, including Garden pool; Gym.')
            ->assertDontSee('including Garden pool; Gym; Hidden spa')
            ->assertSee('Welcome to Demo. We are located in Bali, Indonesia. Check-in is from 14:00 and check-out is at 12:00.')
            ->assertSee('data-key="facility-', false)
            ->assertSee('data-text="Open 07:00 to 20:00.', false);

        $this->get('/demo?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Kami berada di Bali, Indonesia.');
    }

    public function test_hotel_information_narration_skips_missing_data(): void
    {
        Hotel::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. Check-in is from 14:00 and check-out is at 12:00. Below you will find the address')
            ->assertDontSee('We are located in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_lobby_in_every_language(): void
    {
        Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo?lang=ja')->assertOk()->assertSee('サウンドオン');
    }

    public function test_the_rooms_scenes_swap_the_menu_for_a_room_index(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true, 'sort_order' => 0]);
        $hotel->roomTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2200000, 'max_adults' => 4, 'max_children' => 0, 'is_active' => true, 'sort_order' => 1]);
        $hotel->roomTypes()->create(['name' => 'Retired Room', 'slug' => 'retired', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => false]);

        $this->get('/demo/rooms/family-suite?lang=id')
            ->assertOk()
            ->assertSee('class="room-nav"', false)
            ->assertSee('mulai dari IDR 950.000 / malam')
            ->assertDontSee('Retired Room')
            // the way back to the main menu stays in the card
            ->assertSee('class="stage-menu-back"', false)
            ->assertSee('href="'.route('hotel.show', ['hotelSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertDontSee('data-lobby-link="facilities"', false);

        // the open room is marked as current in the index, the others are not
        $index = $this->get('/demo/rooms/family-suite?lang=id')->getContent();
        $navigation = Str::between($index, '<nav class="room-nav">', '</nav>');
        $this->assertStringContainsString('aria-current="page"', Str::after($navigation, 'roomSlug=family-suite') ?: $navigation);
        $this->assertSame(1, substr_count($navigation, 'aria-current'));

        // the lobby keeps the ordinary menu
        $this->get('/demo?lang=id')->assertOk()->assertSee('data-lobby-link="facilities"', false)->assertDontSee('class="room-nav"', false);
    }

    public function test_a_scene_layers_a_cut_out_concierge_when_a_plain_background_is_supplied(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);

        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $character = public_path('images/character.png');
        $background = public_path('images/rooms-bg.png');

        try {
            // Only the cut-out: the scene keeps the artwork that already has her in it.
            file_put_contents($character, $pixel);

            $this->get('/demo/rooms')
                ->assertOk()
                ->assertSee('images/suite.png', false)
                ->assertDontSee('class="stage-character"', false);

            // Cut-out plus a plain background: she is layered in front instead.
            file_put_contents($background, $pixel);

            $this->get('/demo/rooms')
                ->assertOk()
                ->assertSee('class="stage-character"', false)
                ->assertSee('images/rooms-bg.png', false)
                ->assertSee('images/character.png', false)
                ->assertDontSee('images/suite.png', false);

            // The lobby has no plain background of its own, so it is unaffected.
            $this->get('/demo')
                ->assertOk()
                ->assertSee('images/concierge-lobby.png', false)
                ->assertDontSee('class="stage-character"', false);
        } finally {
            @unlink($character);
            @unlink($background);
        }
    }
}
