<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Services\Concierge\HotelConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConciergeBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_requested_rooms(): void
    {
        $hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $room = $hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $conversation = Conversation::create(['hotel_id' => $hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $checkIn = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1] as $offset) {
            RoomInventory::create(['room_type_id' => $room->id, 'stay_date' => $checkIn->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }

        $tools = new HotelConciergeTools($hotel, $conversation, 'en');
        $input = [
            'room_type_slug' => 'deluxe-king', 'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->addDays(2)->toDateString(),
            'adults' => 2, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', [...$input, 'extra_bed' => false]);
        $this->assertSame(2000000.0, $quote['ui']['quote']['grand_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'rooms' => 2]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(2, $booking->room_count);
        $this->assertSame(4000000.0, (float) $booking->total_price);
        $this->assertSame([2, 2], RoomInventory::orderBy('stay_date')->pluck('booked_units')->all());

        $tooMany = $tools->dispatch('create_booking_request', [...$input, 'rooms' => 2]);
        $this->assertNull($tooMany['ui']);
        $this->assertSame(1, Booking::count());
    }
}
