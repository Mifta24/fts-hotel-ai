<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $deluxe;

    private RoomType $suite;

    private string $checkIn;

    private string $checkOut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'front@demo.test',
        ]);

        $this->deluxe = $this->hotel->roomTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 1,
            'extra_bed_available' => true, 'extra_bed_price' => 250000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->suite = $this->hotel->roomTypes()->create([
            'name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2000000, 'max_adults' => 4, 'max_children' => 2,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $this->checkIn = $today->addDays(10)->toDateString();
        $this->checkOut = $today->addDays(13)->toDateString();

        foreach ([$this->deluxe->id => [2, 1000000], $this->suite->id => [1, 2000000]] as $roomTypeId => [$units, $price]) {
            foreach (range(10, 12) as $offset) {
                RoomInventory::create([
                    'room_type_id' => $roomTypeId, 'stay_date' => $today->addDays($offset)->toDateString(),
                    'total_units' => $units, 'booked_units' => 0, 'price' => $price,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stay(array $overrides = []): array
    {
        return [
            'room_type_slug' => 'deluxe-king', 'check_in' => $this->checkIn, 'check_out' => $this->checkOut,
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->stay([
            'guest_name' => 'John Tan', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'special_request' => 'High floor', ...$overrides,
        ]);
    }

    public function test_quote_prices_every_night_for_every_room(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['rooms' => 2, 'extra_bed' => true]))
            ->assertOk()
            ->assertJson([
                'available' => true, 'nights' => 3, 'room_total' => 6000000, 'extra_bed_total' => 1500000,
                'grand_total' => 7500000, 'currency' => 'IDR',
            ]);
    }

    public function test_quote_rejects_invalid_dates_in_the_guest_language(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->stay(['check_in' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.check_in.0', 'Tanggal check-in tidak boleh di masa lalu.');

        $this->postJson('/demo/reservation/quote', $this->stay(['check_out' => $this->checkIn]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.check_out.0', 'Check-out must be after check-in.');
    }

    public function test_quote_rejects_stays_longer_than_the_limit(): void
    {
        $tooLong = CarbonImmutable::parse($this->checkIn)->addDays(31)->toDateString();

        $this->postJson('/demo/reservation/quote', $this->stay(['check_out' => $tooLong]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_out');
    }

    public function test_quote_rejects_a_party_larger_than_the_rooms_can_hold(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['adults' => 3]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.adults.0', 'This room does not fit that many guests. Choose another room or add rooms.');

        $this->postJson('/demo/reservation/quote', $this->stay(['adults' => 3, 'rooms' => 2]))->assertOk();
    }

    public function test_quote_rejects_unknown_and_inactive_rooms(): void
    {
        $this->deluxe->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->stay())->assertUnprocessable()->assertJsonValidationErrors('room_type_slug');
        $this->postJson('/demo/reservation/quote', $this->stay(['room_type_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('room_type_slug');
    }

    public function test_unavailable_room_offers_alternatives_that_fit_the_party(): void
    {
        RoomInventory::where('room_type_id', $this->deluxe->id)->update(['booked_units' => 2]);

        $this->postJson('/demo/reservation/quote', $this->stay())
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_type_slug.0', 'This room is not available for those dates.')
            ->assertJsonPath('alternatives.0.slug', 'family-suite')
            ->assertJsonPath('alternatives.0.total', 6000000);
    }

    public function test_quote_asks_for_more_units_than_are_free_when_booking_several_rooms(): void
    {
        $this->postJson('/demo/reservation/quote', $this->stay(['room_type_slug' => 'family-suite', 'rooms' => 2, 'adults' => 2]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_type_slug.0', 'This room is not available for those dates.');
    }

    public function test_submitting_creates_a_pending_request_holds_inventory_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['rooms' => 2, 'adults' => 3, 'guest_token' => $conversation->guest_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 6000000)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^BK-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame(2, $booking->room_count);
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->guest_phone);
        $this->assertNull($booking->guest_email);
        $this->assertSame('High floor', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame([2, 2, 2], RoomInventory::where('room_type_id', $this->deluxe->id)->orderBy('stay_date')->pluck('booked_units')->all());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: John Tan', $message);
        $this->assertStringContainsString('Rooms: 2', $message);
        $this->assertStringContainsString('Room Type: Deluxe King', $message);
        $this->assertStringContainsString('Special Request: High floor', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:front@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'john@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('john@example.com', $booking->guest_email);
        $this->assertNull($booking->guest_phone);
    }

    public function test_the_last_free_room_can_only_be_requested_once(): void
    {
        $this->postJson('/demo/reservation', $this->request(['room_type_slug' => 'family-suite']))->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['room_type_slug' => 'family-suite', 'guest_name' => 'Late Guest']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_type_slug.0', 'This room is not available for those dates.');

        $this->assertSame(1, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_hotels_reject_reservations(): void
    {
        Hotel::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->stay())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->stay())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->stay())->assertTooManyRequests();
    }

    public function test_cancelling_a_multi_room_booking_releases_every_held_unit(): void
    {
        $this->postJson('/demo/reservation', $this->request(['rooms' => 2, 'adults' => 3]))->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->hotel->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0, 0, 0], RoomInventory::where('room_type_id', $this->deluxe->id)->orderBy('stay_date')->pluck('booked_units')->all());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }
}
