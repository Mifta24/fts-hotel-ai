<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\GuestChangedBooking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestBookingTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $room;

    private Booking $booking;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $this->start = CarbonImmutable::today()->addDays(10);

        foreach (range(0, 9) as $offset) {
            RoomInventory::create(['room_type_id' => $this->room->id, 'stay_date' => $this->start->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => $offset < 2 ? 1 : 0, 'price' => 1000000 + $offset * 100000]);
        }

        $this->booking = Booking::create([
            'reference' => 'BK-GUEST1', 'hotel_id' => $this->hotel->id, 'room_type_id' => $this->room->id, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 1112 223',
            'contact_type' => 'phone', 'room_count' => 1, 'adults' => 2, 'status' => Booking::STATUS_CONFIRMED,
            'check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString(), 'total_price' => 2100000,
        ]);

        $staff = User::factory()->create();
        $this->hotel->users()->attach($staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function credentials(array $override = []): array
    {
        return ['reference' => 'bk-guest1', 'contact' => '0811 1112 223', ...$override];
    }

    private function booked(int $offset): int
    {
        return RoomInventory::whereDate('stay_date', $this->start->addDays($offset)->toDateString())->value('booked_units');
    }

    public function test_a_guest_finds_their_booking_with_the_reference_and_matching_contact_only(): void
    {
        $this->postJson('/demo/booking/lookup', $this->credentials())->assertOk()->assertJsonPath('reference', 'BK-GUEST1')->assertJsonPath('changeable', true);
        $this->postJson('/demo/booking/lookup', $this->credentials(['contact' => '0899 000 111']))->assertNotFound();
        $this->postJson('/demo/booking/lookup', $this->credentials(['reference' => 'BK-NOPE00']))->assertNotFound();

        $this->booking->update(['guest_phone' => null, 'guest_email' => 'Ayu@Example.test']);
        $this->postJson('/demo/booking/lookup', $this->credentials(['contact' => 'ayu@example.test']))->assertOk();
    }

    public function test_a_booking_of_another_hotel_cannot_be_found_through_this_one(): void
    {
        Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->postJson('/other/booking/lookup', $this->credentials())->assertNotFound();
    }

    public function test_rescheduling_moves_the_rooms_reprices_the_stay_and_asks_the_hotel_to_confirm_again(): void
    {
        Notification::fake();

        $this->postJson('/demo/booking/reschedule', $this->credentials(['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString()]))
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total', 2900000);

        $this->assertSame(0, $this->booked(0));
        $this->assertSame(0, $this->booked(1));
        $this->assertSame(0, $this->booked(2));
        $this->assertSame(1, $this->booked(4));
        $this->assertSame(1, $this->booked(5));
        $this->assertSame(Booking::STATUS_PENDING, $this->booking->fresh()->status);
        Notification::assertSentTo($this->hotel->users()->first(), GuestChangedBooking::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.rescheduled', 'actor' => 'guest']);
    }

    public function test_unavailable_nights_leave_the_booking_exactly_as_it_was(): void
    {
        RoomInventory::whereDate('stay_date', $this->start->addDays(5)->toDateString())->update(['booked_units' => 1]);

        $this->postJson('/demo/booking/reschedule', $this->credentials(['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString()]))
            ->assertUnprocessable();

        $fresh = $this->booking->fresh();
        $this->assertSame($this->start->toDateString(), $fresh->check_in->toDateString());
        $this->assertSame(Booking::STATUS_CONFIRMED, $fresh->status);
        $this->assertSame(1, $this->booked(0));
        $this->assertSame(1, $this->booked(1));
        $this->assertSame(0, $this->booked(4));
    }

    public function test_a_stay_can_overlap_its_own_nights_when_moved_by_one_day(): void
    {
        $this->postJson('/demo/booking/reschedule', $this->credentials(['check_in' => $this->start->addDay()->toDateString(), 'check_out' => $this->start->addDays(3)->toDateString()]))->assertOk();

        $this->assertSame(0, $this->booked(0));
        $this->assertSame(1, $this->booked(1));
        $this->assertSame(1, $this->booked(2));
    }

    public function test_cancelling_releases_the_rooms_once_and_a_cancelled_booking_cannot_be_changed(): void
    {
        Notification::fake();

        $this->postJson('/demo/booking/cancel', $this->credentials())->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(0, $this->booked(0));

        $this->postJson('/demo/booking/cancel', $this->credentials())->assertStatus(409);
        $this->postJson('/demo/booking/reschedule', $this->credentials(['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(5)->toDateString()]))->assertStatus(409);
        $this->assertSame(0, $this->booked(4));
        Notification::assertSentTimes(GuestChangedBooking::class, 1);
    }

    public function test_a_stay_that_has_already_started_cannot_be_changed_online(): void
    {
        $this->booking->update(['check_in' => CarbonImmutable::yesterday()->toDateString(), 'check_out' => CarbonImmutable::tomorrow()->toDateString()]);

        $this->postJson('/demo/booking/cancel', $this->credentials())->assertStatus(409);
        $this->postJson('/demo/booking/lookup', $this->credentials())->assertOk()->assertJsonPath('changeable', false);
    }

    public function test_the_management_page_opens_in_the_guests_language(): void
    {
        $this->get('/demo/booking?lang=ja')->assertOk()->assertSee('ご予約の確認・変更');
        $this->get('/demo/booking?lang=id')->assertOk()->assertSee('Kelola reservasi saya');
    }
}
