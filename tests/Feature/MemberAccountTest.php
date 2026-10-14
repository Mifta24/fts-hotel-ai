<?php

namespace Tests\Feature;

use App\Core\Member\Models\Member;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Services\Concierge\HotelConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberAccountTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $room;

    private Member $member;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::factory()->withMembers()->create(['slug' => 'demo', 'currency' => 'IDR']);
        $this->room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $this->member = Member::factory()->forHotel($this->hotel)->create(['name' => 'Ayu', 'email' => 'ayu@example.test']);
        $this->start = CarbonImmutable::today()->addDays(10);

        foreach (range(0, 9) as $offset) {
            RoomInventory::create(['room_type_id' => $this->room->id, 'stay_date' => $this->start->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => $offset < 2 ? 1 : 0, 'price' => 1000000]);
        }
    }

    private function booking(string $reference, ?Member $member, array $override = []): Booking
    {
        return Booking::create([
            'reference' => $reference, 'hotel_id' => $this->hotel->id, 'room_type_id' => $this->room->id, 'member_id' => $member?->id, 'guest_name' => 'Ayu',
            'guest_email' => 'ayu@example.test', 'room_count' => 1, 'adults' => 2, 'status' => Booking::STATUS_CONFIRMED,
            'check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString(), 'total_price' => 2000000, ...$override,
        ]);
    }

    private function booked(int $offset): int
    {
        return RoomInventory::whereDate('stay_date', $this->start->addDays($offset)->toDateString())->value('booked_units');
    }

    private function account(string $suffix = '')
    {
        return '/demo/member'.$suffix;
    }

    public function test_the_account_lists_only_the_members_own_reservations(): void
    {
        $other = Member::factory()->forHotel($this->hotel)->create();
        $this->booking('BK-AAAAAA', $this->member);
        $this->booking('BK-BBBBBB', $other);
        $this->booking('BK-CCCCCC', null);

        $this->actingAs($this->member, 'member')->get($this->account())
            ->assertOk()
            ->assertSee('BK-AAAAAA')
            ->assertDontSee('BK-BBBBBB')
            ->assertDontSee('BK-CCCCCC');
    }

    public function test_the_page_follows_the_requested_language_then_the_members_own(): void
    {
        $this->member->update(['locale' => 'ja']);
        $this->actingAs($this->member, 'member');

        $this->get($this->account())->assertSee('ご予約一覧');
        $this->get($this->account('?lang=id'))->assertSee('Reservasi saya');
    }

    public function test_a_new_member_is_asked_for_their_name_then_can_save_their_details(): void
    {
        $member = Member::factory()->forHotel($this->hotel)->withoutProfile()->create();

        $this->actingAs($member, 'member')->get($this->account('?lang=en'))->assertSee('Tell us your name');

        $this->put($this->account('/profile'), ['name' => 'Budi', 'phone' => '+62 811 222 333', 'locale' => 'id'])->assertRedirect()->assertSessionHas('status');

        $member->refresh();
        $this->assertSame('Budi', $member->name);
        $this->assertSame('+62 811 222 333', $member->phone);
        $this->assertSame('id', $member->locale);
    }

    public function test_invalid_details_are_refused_and_nothing_changes(): void
    {
        $this->actingAs($this->member, 'member');

        $this->put($this->account('/profile'), ['name' => '', 'phone' => 'call me', 'locale' => 'fr'])->assertSessionHasErrors(['name', 'phone', 'locale']);

        $this->assertSame('Ayu', $this->member->fresh()->name);
    }

    public function test_the_member_moves_their_own_stay_without_typing_a_reference(): void
    {
        $booking = $this->booking('BK-AAAAAA', $this->member);

        $this->actingAs($this->member, 'member')
            ->post($this->account('/bookings/BK-AAAAAA/reschedule'), ['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString()])
            ->assertRedirect()->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame($this->start->addDays(4)->toDateString(), $booking->check_in->toDateString());
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame(0, $this->booked(0));
        $this->assertSame(1, $this->booked(4));
    }

    public function test_moving_to_unavailable_nights_changes_nothing(): void
    {
        $booking = $this->booking('BK-AAAAAA', $this->member, ['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString()]);

        $this->actingAs($this->member, 'member')
            ->post($this->account('/bookings/BK-AAAAAA/reschedule'), ['check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString()])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame($this->start->addDays(4)->toDateString(), $booking->fresh()->check_in->toDateString());
    }

    public function test_the_member_cancels_their_own_reservation_and_the_rooms_are_released(): void
    {
        $booking = $this->booking('BK-AAAAAA', $this->member);

        $this->actingAs($this->member, 'member')->post($this->account('/bookings/bk-aaaaaa/cancel'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $this->booked(0));
    }

    public function test_a_stay_that_has_started_or_was_cancelled_cannot_be_changed(): void
    {
        $started = $this->booking('BK-STARTD', $this->member, ['check_in' => CarbonImmutable::today()->subDay()->toDateString(), 'check_out' => CarbonImmutable::today()->addDay()->toDateString()]);
        $cancelled = $this->booking('BK-CANCLD', $this->member, ['status' => Booking::STATUS_CANCELLED]);

        $this->actingAs($this->member, 'member');
        $this->post($this->account('/bookings/BK-STARTD/cancel'))->assertSessionHas('error');
        $this->post($this->account('/bookings/BK-CANCLD/cancel'))->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CONFIRMED, $started->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->fresh()->status);
    }

    public function test_a_member_cannot_touch_someone_elses_reservation(): void
    {
        $other = Member::factory()->forHotel($this->hotel)->create();
        $theirs = $this->booking('BK-THEIRS', $other);
        $unclaimed = $this->booking('BK-UNCLAM', null);

        $this->actingAs($this->member, 'member');
        $dates = ['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString()];

        $this->post($this->account('/bookings/BK-THEIRS/cancel'))->assertNotFound();
        $this->post($this->account('/bookings/BK-THEIRS/reschedule'), $dates)->assertNotFound();
        $this->post($this->account('/bookings/BK-UNCLAM/cancel'))->assertNotFound();

        $this->assertSame(Booking::STATUS_CONFIRMED, $theirs->fresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $unclaimed->fresh()->status);
    }

    public function test_a_reservation_made_while_signed_in_belongs_to_the_member_and_the_form_is_prefilled(): void
    {
        $this->member->update(['phone' => '+62 811 000 111']);
        $this->actingAs($this->member, 'member');

        $this->get('/demo/reservation')->assertOk()->assertSee('value="Ayu"', false)->assertSee('value="+62 811 000 111"', false);

        $this->postJson('/demo/reservation', [
            'room_type_slug' => 'deluxe', 'check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'locale' => 'en', 'guest_name' => 'Ayu', 'contact_type' => 'whatsapp', 'contact_value' => '+62 811 000 111',
        ])->assertCreated();

        $this->assertSame($this->member->id, Booking::latest('id')->firstOrFail()->member_id);
    }

    public function test_a_reservation_by_a_guest_who_is_not_signed_in_stays_anonymous(): void
    {
        $this->get('/demo/reservation')->assertOk()->assertDontSee('value="Ayu"', false);

        $this->postJson('/demo/reservation', [
            'room_type_slug' => 'deluxe', 'check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'locale' => 'en', 'guest_name' => 'Walk In', 'contact_type' => 'email', 'contact_value' => 'walk@example.test',
        ])->assertCreated();

        $this->assertNull(Booking::latest('id')->firstOrFail()->member_id);
    }

    public function test_signing_out_ends_the_session_on_the_member_pages(): void
    {
        $this->actingAs($this->member, 'member')->post($this->account('/logout'))->assertRedirect();

        $this->assertGuest('member');
        $this->get($this->account())->assertRedirect(route('member.login', ['hotelSlug' => 'demo']));
    }

    public function test_the_hotel_page_offers_member_sign_in_only_when_the_add_on_is_on(): void
    {
        $this->get('/demo/staff')->assertOk()->assertSee('/demo/member', false);

        $plain = Hotel::factory()->create(['slug' => 'plain']);

        $this->get('/plain/staff')->assertOk()->assertDontSee('/plain/member', false);
    }

    public function test_a_booking_the_ai_concierge_makes_for_a_signed_in_member_belongs_to_them(): void
    {
        $conversation = Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
        $input = [
            'room_type_slug' => 'deluxe', 'check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(6)->toDateString(),
            'adults' => 2, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ];

        (new HotelConciergeTools($this->hotel, $conversation, 'en'))->dispatch('create_booking_request', $input);
        $this->assertNull(Booking::firstOrFail()->member_id);

        $this->actingAs($this->member, 'member');
        (new HotelConciergeTools($this->hotel, $conversation, 'en'))->dispatch('create_booking_request', [...$input, 'check_in' => $this->start->addDays(6)->toDateString(), 'check_out' => $this->start->addDays(8)->toDateString()]);

        $this->assertSame([null, $this->member->id], Booking::orderBy('id')->pluck('member_id')->all());
    }

    public function test_the_chat_form_starts_with_the_members_name_and_phone_only_when_signed_in(): void
    {
        $this->member->update(['phone' => '+62 811 000 111']);

        $this->get('/demo')->assertOk()->assertSee('data-member-name=""', false);

        $this->actingAs($this->member, 'member')->get('/demo')->assertOk()
            ->assertSee('data-member-name="Ayu"', false)
            ->assertSee('data-member-phone="+62 811 000 111"', false);
    }
}
