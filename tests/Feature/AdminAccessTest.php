<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Hotel $otherHotel;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->otherHotel = Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->staff = User::factory()->create();
        $this->hotel->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function foreignBooking(): Booking
    {
        $room = $this->otherHotel->roomTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => true]);

        return Booking::create([
            'reference' => 'BK-FOREIGN', 'hotel_id' => $this->otherHotel->id, 'room_type_id' => $room->id, 'guest_name' => 'Foreign Guest',
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
    }

    private function foreignHandover(): HandoverRequest
    {
        $conversation = Conversation::create(['hotel_id' => $this->otherHotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        return HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Foreign complaint']);
    }

    public function test_visitors_are_sent_to_the_login_page_for_every_admin_screen(): void
    {
        foreach (['admin.dashboard', 'admin.room-types.index', 'admin.knowledge-items.index', 'admin.bookings.index', 'admin.handovers.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }

    public function test_staff_only_see_bookings_of_their_own_hotel(): void
    {
        $this->foreignBooking();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertDontSee('BK-FOREIGN')->assertDontSee('Foreign Guest');
    }

    public function test_staff_cannot_change_another_hotels_booking(): void
    {
        $booking = $this->foreignBooking();

        $this->actingAs($this->staff)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertNotFound();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_staff_cannot_read_reply_to_or_resolve_another_hotels_handover(): void
    {
        $handover = $this->foreignHandover();

        $this->actingAs($this->staff)->get(route('admin.handovers.show', $handover))->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'Hello'])->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertNotFound();

        $this->assertSame(0, ConversationMessage::count());
        $this->assertSame(HandoverRequest::STATUS_OPEN, $handover->fresh()->status);
    }

    public function test_staff_reply_reaches_the_guest_and_resolving_returns_the_conversation_to_the_ai(): void
    {
        $conversation = Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en', 'status' => Conversation::STATUS_HANDED_OVER]);
        $handover = HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Noisy room']);

        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'We are on it.'])->assertRedirect();
        $this->assertSame('We are on it.', $conversation->messages()->where('role', ConversationMessage::ROLE_STAFF)->value('content'));

        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertRedirect(route('admin.handovers.index'));
        $this->assertSame(Conversation::STATUS_ACTIVE, $conversation->fresh()->status);
        $this->assertSame(HandoverRequest::STATUS_RESOLVED, $handover->fresh()->status);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertSessionHasErrors();
        }

        $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_staff_can_give_a_facility_a_photo_and_only_a_real_url_is_accepted(): void
    {
        $item = $this->hotel->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true]);
        $form = ['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => 1];

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'ftp://example.test/pool.jpg'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'https://example.test/pool.jpg'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/pool.jpg', $item->fresh()->image_url);
    }

    public function test_a_cancelled_booking_cannot_be_confirmed_again_and_its_rooms_are_released_once(): void
    {
        $room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $stay = now()->addDays(3)->toDateString();
        $inventory = RoomInventory::create(['room_type_id' => $room->id, 'stay_date' => $stay, 'total_units' => 2, 'booked_units' => 1, 'price' => 1]);
        $booking = Booking::create([
            'reference' => 'BK-OWN', 'hotel_id' => $this->hotel->id, 'room_type_id' => $room->id, 'guest_name' => 'Own Guest',
            'check_in' => $stay, 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1, 'room_count' => 1,
        ]);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])->assertSessionHasErrors('status');
        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $inventory->fresh()->booked_units);
    }

    public function test_bookings_of_a_deleted_room_type_still_show_and_the_name_can_be_reused(): void
    {
        $room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        Booking::create([
            'reference' => 'BK-OLD', 'hotel_id' => $this->hotel->id, 'room_type_id' => $room->id, 'guest_name' => 'Old Guest',
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);

        $this->actingAs($this->staff)->delete(route('admin.room-types.destroy', $room))->assertRedirect();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee('Deluxe');
        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk()->assertSee('Old Guest');

        $this->actingAs($this->staff)
            ->post(route('admin.room-types.store'), ['name' => 'Deluxe', 'max_adults' => 2, 'max_children' => 0, 'base_price' => 100, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->hotel->roomTypes()->count());
    }

    public function test_only_real_web_addresses_are_accepted_for_room_photos(): void
    {
        $form = ['name' => 'Deluxe', 'max_adults' => 2, 'max_children' => 0, 'base_price' => 100, 'is_active' => 1];

        $this->actingAs($this->staff)
            ->post(route('admin.room-types.store'), [...$form, 'new_images' => [['url' => 'javascript:alert(1)']]])
            ->assertSessionHasErrors('new_images.0.url');

        $this->actingAs($this->staff)
            ->post(route('admin.room-types.store'), [...$form, 'new_images' => [['url' => 'https://example.test/room.jpg', 'alt' => 'Room']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/room.jpg', $this->hotel->roomTypes()->firstOrFail()->images()->firstOrFail()->image_url);
    }
}
