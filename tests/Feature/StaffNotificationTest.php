<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\User;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Concierge\HotelConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_hear_about_new_reservations_and_handovers_but_others_do_not(): void
    {
        Notification::fake();

        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $room = $hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $active = User::factory()->create();
        $suspended = User::factory()->create();
        $hotel->users()->attach($active->id, ['role' => 'staff', 'status' => 'active']);
        $hotel->users()->attach($suspended->id, ['role' => 'staff', 'status' => 'suspended']);

        $checkIn = CarbonImmutable::today()->addDays(4);
        RoomInventory::create(['room_type_id' => $room->id, 'stay_date' => $checkIn->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 1000000]);

        $this->postJson('/demo/reservation', [
            'room_type_slug' => 'deluxe', 'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->addDay()->toDateString(),
            'adults' => 2, 'rooms' => 1, 'guest_name' => 'Ayu', 'contact_type' => 'phone', 'contact_value' => '+62 811 111 222',
        ])->assertCreated();

        Notification::assertSentTo($active, NewBookingRequest::class);
        Notification::assertNotSentTo($suspended, NewBookingRequest::class);

        $conversation = Conversation::create(['hotel_id' => $hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
        $tools = new HotelConciergeTools($hotel, $conversation, 'en');
        $tools->dispatch('request_human_handover', ['reason' => 'complaint', 'summary' => 'Noisy room']);
        $tools->dispatch('request_human_handover', ['reason' => 'complaint', 'summary' => 'Noisy room, again']);

        Notification::assertSentToTimes($active, NewHandoverRequest::class, 1);
    }

    public function test_the_mail_describes_the_request_and_links_to_the_admin(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $room = $hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $booking = $hotel->bookings()->create([
            'reference' => 'BK-MAIL1', 'room_type_id' => $room->id, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811', 'contact_type' => 'whatsapp', 'room_count' => 1,
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);

        $mail = (new NewBookingRequest($booking))->toMail(User::factory()->make());

        $this->assertStringContainsString('BK-MAIL1', $mail->subject);
        $this->assertSame(route('admin.bookings.index', ['status' => 'pending']), $mail->actionUrl);
    }
}
