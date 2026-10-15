<?php

namespace Tests\Feature;

use App\Core\Member\Models\Member;
use App\Core\Member\Models\MemberPoint;
use App\Core\Member\Services\PointsLedger;
use App\Core\Member\Support\LoyaltyProgram;
use App\Core\Member\Support\LoyaltyTier;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Concierge\HotelConciergeTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberPlusTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $room;

    private Member $member;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::factory()->withLoyalty()->create(['slug' => 'demo', 'currency' => 'IDR']);
        $this->room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $this->member = Member::factory()->forHotel($this->hotel)->create(['name' => 'Ayu', 'email' => 'ayu@example.test']);
        $this->start = CarbonImmutable::today()->addDays(10);

        foreach (range(0, 9) as $offset) {
            RoomInventory::create(['room_type_id' => $this->room->id, 'stay_date' => $this->start->addDays($offset)->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 1000000]);
        }
    }

    private function givePoints(Member $member, int $points): void
    {
        $member->points()->create(['type' => MemberPoint::TYPE_ADJUST, 'points' => $points, 'note' => 'seed']);
    }

    private function stay(array $override = []): array
    {
        return [
            'room_type_slug' => 'deluxe', 'check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString(),
            'adults' => 2, 'children' => 0, 'rooms' => 1, 'locale' => 'en', ...$override,
        ];
    }

    private function booking(string $reference, ?Member $member, array $override = []): Booking
    {
        return Booking::create([
            'reference' => $reference, 'hotel_id' => $this->hotel->id, 'room_type_id' => $this->room->id, 'member_id' => $member?->id, 'guest_name' => 'Ayu',
            'room_count' => 1, 'adults' => 2, 'status' => Booking::STATUS_CONFIRMED,
            'check_in' => CarbonImmutable::today()->subDays(5)->toDateString(), 'check_out' => CarbonImmutable::today()->subDays(3)->toDateString(), 'total_price' => 2000000, ...$override,
        ]);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $this->hotel->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        return $user;
    }

    public function test_the_program_turns_spend_into_points_and_points_into_a_tier(): void
    {
        $program = new LoyaltyProgram(10000, [new LoyaltyTier('Gold', 1000, 10), new LoyaltyTier('Silver', 200, 5)]);

        $this->assertSame(['Silver', 'Gold'], array_map(fn (LoyaltyTier $tier) => $tier->name, $program->tiers));
        $this->assertSame(199, $program->pointsFor(1999999));
        $this->assertSame(0, $program->pointsFor(9999));
        $this->assertSame(0, $program->pointsFor(-5));

        $this->assertNull($program->tierFor(199));
        $this->assertSame('Silver', $program->tierFor(200)->name);
        $this->assertSame('Silver', $program->tierFor(999)->name);
        $this->assertSame('Gold', $program->tierFor(5000)->name);

        $this->assertSame('Silver', $program->nextTierAfter(0)->name);
        $this->assertSame('Gold', $program->nextTierAfter(200)->name);
        $this->assertNull($program->nextTierAfter(1000));

        $this->assertSame(0.0, $program->discountFor(199));
        $this->assertSame(5.0, $program->discountFor(200));
        $this->assertSame(10.0, $program->discountFor(1000));
    }

    public function test_a_source_pays_out_once_and_is_taken_back_once(): void
    {
        $ledger = app(PointsLedger::class);
        $booking = $this->booking('BK-LEDGER', $this->member);

        $this->assertNotNull($ledger->earn($this->member, 200, $booking));
        $this->assertNull($ledger->earn($this->member, 200, $booking));
        $this->assertSame(200, $this->member->pointsBalance());

        $this->assertNotNull($ledger->reverse($this->member, $booking));
        $this->assertNull($ledger->reverse($this->member, $booking));
        $this->assertSame(0, $this->member->pointsBalance());

        $this->assertNull($ledger->reverse($this->member, $this->booking('BK-NEVER1', $this->member)));
    }

    public function test_finished_stays_are_completed_and_then_earn_points_once(): void
    {
        $earns = $this->booking('BK-DONE01', $this->member);
        $this->booking('BK-PENDNG', $this->member, ['status' => Booking::STATUS_PENDING]);
        $this->booking('BK-CANCLD', $this->member, ['status' => Booking::STATUS_CANCELLED]);
        $this->booking('BK-FUTURE', $this->member, ['check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString()]);
        $this->booking('BK-GUEST1', null);

        $this->artisan('members:award-points')->assertSuccessful();
        $this->assertSame(0, MemberPoint::count(), 'a stay that is not completed yet earns nothing');

        $this->artisan('bookings:complete-stays')->assertSuccessful();
        $this->artisan('members:award-points')->assertSuccessful();
        $this->artisan('members:award-points')->assertSuccessful();

        $this->assertSame(200, $this->member->pointsBalance());
        $this->assertSame(1, MemberPoint::count());
        $this->assertDatabaseHas('member_points', ['member_id' => $this->member->id, 'type' => 'earn', 'points' => 200, 'source_id' => $earns->id]);
    }

    public function test_a_stay_cancelled_after_it_paid_out_gives_the_points_back(): void
    {
        $booking = $this->booking('BK-DONE01', $this->member);
        $this->artisan('bookings:complete-stays')->assertSuccessful();
        $this->artisan('members:award-points')->assertSuccessful();
        $this->assertSame(200, $this->member->pointsBalance());

        $booking->update(['status' => Booking::STATUS_CANCELLED]);
        $this->artisan('members:award-points')->assertSuccessful();
        $this->artisan('members:award-points')->assertSuccessful();

        $this->assertSame(0, $this->member->pointsBalance());
        $this->assertSame(2, MemberPoint::count());
    }

    public function test_a_hotel_without_the_plus_add_on_earns_and_discounts_nothing(): void
    {
        $this->hotel->update(['features' => [Hotel::FEATURE_MEMBER]]);
        $this->booking('BK-DONE01', $this->member, ['status' => Booking::STATUS_COMPLETED]);
        $this->givePoints($this->member, 5000);

        $this->artisan('members:award-points')->assertSuccessful();
        $this->assertSame(5000, $this->member->pointsBalance());

        $this->actingAs($this->member, 'member');
        $this->postJson('/demo/reservation/quote', $this->stay())->assertOk()->assertJsonPath('grand_total', 2000000)->assertJsonPath('member_discount_percent', 0);
        $this->get('/demo/member?lang=en')->assertOk()->assertDontSee('My membership');
        $this->actingAs($this->owner())->post(route('admin.members.points', $this->member->id), ['points' => 10, 'note' => 'x'])->assertNotFound();
        $this->get(route('admin.loyalty.edit'))->assertNotFound();
    }

    public function test_the_members_tier_discount_prices_the_quote_for_them_only(): void
    {
        $this->givePoints($this->member, 250);

        $this->postJson('/demo/reservation/quote', $this->stay())->assertOk()->assertJsonPath('grand_total', 2000000)->assertJsonPath('member_discount_percent', 0);

        $this->actingAs($this->member, 'member');
        $this->postJson('/demo/reservation/quote', $this->stay())->assertOk()
            ->assertJsonPath('subtotal', 2000000)
            ->assertJsonPath('member_discount_percent', 5)
            ->assertJsonPath('discount_amount', 100000)
            ->assertJsonPath('grand_total', 1900000);
    }

    public function test_a_member_below_the_first_tier_pays_the_full_price(): void
    {
        $this->givePoints($this->member, 199);

        $this->actingAs($this->member, 'member')->postJson('/demo/reservation/quote', $this->stay())->assertOk()->assertJsonPath('grand_total', 2000000);
    }

    public function test_a_booking_keeps_the_discount_it_was_made_with(): void
    {
        $this->givePoints($this->member, 1200);

        $this->actingAs($this->member, 'member')->postJson('/demo/reservation', [...$this->stay(), 'guest_name' => 'Ayu', 'contact_type' => 'email', 'contact_value' => 'ayu@example.test'])
            ->assertCreated()->assertJsonPath('total', 1800000);

        $booking = Booking::firstOrFail();
        $this->assertSame($this->member->id, $booking->member_id);
        $this->assertSame(10.0, (float) $booking->discount_percent);
        $this->assertSame(200000.0, (float) $booking->discount_amount);
        $this->assertSame(1800000.0, (float) $booking->total_price);

        $this->givePoints($this->member, 10000);
        $this->assertSame(10.0, (float) $booking->fresh()->discount_percent);
    }

    public function test_a_guest_booking_has_no_discount(): void
    {
        $this->givePoints($this->member, 1200);

        $this->postJson('/demo/reservation', [...$this->stay(), 'guest_name' => 'Walk In', 'contact_type' => 'email', 'contact_value' => 'walk@example.test'])->assertCreated()->assertJsonPath('total', 2000000);

        $this->assertSame(0.0, (float) Booking::firstOrFail()->discount_percent);
    }

    public function test_moving_a_discounted_stay_reprices_it_with_the_same_discount(): void
    {
        $this->givePoints($this->member, 250);
        $booking = $this->booking('BK-MOVE01', $this->member, [
            'check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString(), 'total_price' => 1900000, 'discount_percent' => 5, 'discount_amount' => 100000,
        ]);
        RoomInventory::whereDate('stay_date', '>=', $this->start->toDateString())->whereDate('stay_date', '<', $this->start->addDays(2)->toDateString())->update(['booked_units' => 1]);

        $this->actingAs($this->member, 'member')
            ->post('/demo/member/bookings/BK-MOVE01/reschedule', ['check_in' => $this->start->addDays(4)->toDateString(), 'check_out' => $this->start->addDays(7)->toDateString()])
            ->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame(5.0, (float) $booking->discount_percent);
        $this->assertSame(150000.0, (float) $booking->discount_amount);
        $this->assertSame(2850000.0, (float) $booking->total_price);
    }

    public function test_the_ai_concierge_quotes_and_books_with_the_members_discount(): void
    {
        $this->givePoints($this->member, 250);
        $conversation = Conversation::create(['hotel_id' => $this->hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
        $input = ['room_type_slug' => 'deluxe', 'check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString(), 'adults' => 2];
        $tools = new HotelConciergeTools($this->hotel, $conversation, 'en');

        $this->assertSame(2000000.0, $tools->dispatch('check_availability', $input)['ui']['quote']['grand_total']);

        $this->actingAs($this->member, 'member');
        $quote = $tools->dispatch('check_availability', $input)['ui']['quote'];
        $this->assertSame(1900000.0, $quote['grand_total']);
        $this->assertEquals(5, $quote['member_discount_percent']);

        $tools->dispatch('create_booking_request', [...$input, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222']);
        $this->assertSame(1900000.0, (float) Booking::firstOrFail()->total_price);
    }

    public function test_the_account_shows_tier_points_progress_and_history_for_the_member_only(): void
    {
        $this->givePoints($this->member, 250);
        $other = Member::factory()->forHotel($this->hotel)->create();
        $other->points()->create(['type' => 'adjust', 'points' => 777, 'note' => 'someone elses gift']);

        $this->actingAs($this->member, 'member')->get('/demo/member?lang=en')->assertOk()
            ->assertSee('My membership')
            ->assertSee('Silver')
            ->assertSee('5% off every stay')
            ->assertSee('750 more points to reach Gold')
            ->assertDontSee('someone elses gift');

        $this->get('/demo/member?lang=id')->assertSee('Diskon 5% untuk setiap reservasi');
    }

    public function test_the_discount_shows_on_the_members_booking_and_in_the_admin(): void
    {
        $this->booking('BK-DISC01', $this->member, ['check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString(), 'total_price' => 1900000, 'discount_percent' => 5, 'discount_amount' => 100000]);

        $this->actingAs($this->member, 'member')->get('/demo/member?lang=en')->assertSee('Member discount')->assertSee('5% (−IDR 100.000)');
        $this->actingAs($this->owner())->get(route('admin.bookings.show', Booking::firstOrFail()))->assertSee('Member discount');
    }

    public function test_staff_adjust_points_with_a_reason_and_it_is_recorded(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.members.points', $this->member->id), ['points' => 300, 'note' => 'Birthday gift'])->assertRedirect()->assertSessionHas('status');
        $this->post(route('admin.members.points', $this->member->id), ['points' => -50, 'note' => 'Correction'])->assertRedirect();

        $this->assertSame(250, $this->member->pointsBalance());
        $this->assertDatabaseHas('member_points', ['member_id' => $this->member->id, 'type' => 'adjust', 'points' => 300, 'note' => 'Birthday gift', 'actor_name' => $owner->name]);
        $this->assertDatabaseHas('audit_logs', ['hotel_id' => $this->hotel->id, 'action' => 'member.points']);
        $this->get(route('admin.members.show', $this->member->id))->assertSee('Birthday gift')->assertSee('Silver');
        $this->get(route('admin.members.index'))->assertSee('Silver');
    }

    public function test_a_point_adjustment_needs_a_reason_a_real_amount_and_one_of_this_hotels_members(): void
    {
        $this->actingAs($this->owner());
        $outsider = Member::factory()->forHotel(Hotel::factory()->withLoyalty()->create())->create();

        $this->post(route('admin.members.points', $this->member->id), ['points' => 0, 'note' => 'x'])->assertSessionHasErrors('points');
        $this->post(route('admin.members.points', $this->member->id), ['points' => 5000000, 'note' => 'x'])->assertSessionHasErrors('points');
        $this->post(route('admin.members.points', $this->member->id), ['points' => 10, 'note' => ''])->assertSessionHasErrors('note');
        $this->post(route('admin.members.points', $outsider->id), ['points' => 10, 'note' => 'x'])->assertNotFound();

        $this->assertSame(0, MemberPoint::count());
    }

    public function test_staff_without_the_members_ability_cannot_adjust_points(): void
    {
        $staff = User::factory()->create();
        $this->hotel->users()->attach($staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($staff)->post(route('admin.members.points', $this->member->id), ['points' => 10, 'note' => 'x'])->assertForbidden();
    }

    public function test_the_owner_sets_the_program_and_new_prices_follow_it(): void
    {
        $this->actingAs($this->owner());

        $this->get(route('admin.loyalty.edit'))->assertOk()->assertSee('Silver');

        $this->put(route('admin.loyalty.update'), [
            'spend_per_point' => 5000,
            'tiers' => [
                ['name' => 'Gold', 'min_points' => 1000, 'discount_percent' => 12.5],
                ['name' => 'Bronze', 'min_points' => 100, 'discount_percent' => 2],
                ['name' => '', 'min_points' => '', 'discount_percent' => ''],
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $program = $this->hotel->fresh()->loyaltyProgram();
        $this->assertSame(5000.0, $program->spendPerPoint);
        $this->assertSame(['Bronze', 'Gold'], array_map(fn (LoyaltyTier $tier) => $tier->name, $program->tiers));
        $this->assertSame(12.5, $program->discountFor(1000));

        $this->givePoints($this->member, 150);
        $this->actingAs($this->member, 'member')->postJson('/demo/reservation/quote', $this->stay())->assertJsonPath('member_discount_percent', 2)->assertJsonPath('grand_total', 1960000);
    }

    public function test_an_invalid_program_is_refused_and_nothing_changes(): void
    {
        $this->actingAs($this->owner());
        $row = fn (string $name, int $points) => ['name' => $name, 'min_points' => $points, 'discount_percent' => 5];

        $this->put(route('admin.loyalty.update'), ['spend_per_point' => 0, 'tiers' => [$row('A', 0)]])->assertSessionHasErrors('spend_per_point');
        $this->put(route('admin.loyalty.update'), ['spend_per_point' => 100, 'tiers' => [['name' => '', 'min_points' => '', 'discount_percent' => '']]])->assertSessionHasErrors('tiers');
        $this->put(route('admin.loyalty.update'), ['spend_per_point' => 100, 'tiers' => [$row('A', 100), $row('B', 100)]])->assertSessionHasErrors('tiers');
        $this->put(route('admin.loyalty.update'), ['spend_per_point' => 100, 'tiers' => [['name' => 'A', 'min_points' => 0, 'discount_percent' => 101]]])->assertSessionHasErrors('tiers.0.discount_percent');

        $this->assertNull($this->hotel->fresh()->setting('loyalty'));
    }

    public function test_only_the_owner_edits_the_program(): void
    {
        $desk = User::factory()->create();
        $this->hotel->users()->attach($desk->id, ['role' => 'front_desk', 'status' => 'active']);

        $this->actingAs($desk)->get(route('admin.loyalty.edit'))->assertForbidden();
        $this->put(route('admin.loyalty.update'), ['spend_per_point' => 100, 'tiers' => [['name' => 'A', 'min_points' => 0, 'discount_percent' => 5]]])->assertForbidden();
    }

    public function test_the_platform_admin_switches_plus_independently_of_member(): void
    {
        $hotel = Hotel::factory()->withMembers()->create();
        $admin = User::factory()->create();
        $admin->is_platform_admin = true;
        $admin->save();

        $this->actingAs($admin)->patch(route('platform.hotels.features', $hotel), ['feature' => 'member_plus', 'enabled' => 1])->assertRedirect();
        $this->assertTrue($hotel->fresh()->supportsLoyalty());

        $this->patch(route('platform.hotels.features', $hotel), ['feature' => 'member', 'enabled' => 0])->assertRedirect();
        $this->assertFalse($hotel->fresh()->supportsMembers());
        $this->assertFalse($hotel->fresh()->supportsLoyalty());
        $this->assertTrue($hotel->fresh()->hasFeature('member_plus'));
    }

    public function test_a_confirmed_stay_is_completed_on_its_check_out_day_and_nothing_else_is(): void
    {
        $over = $this->booking('BK-OVER01', null, ['check_out' => CarbonImmutable::today()->toDateString()]);
        $tomorrow = $this->booking('BK-TMRW01', null, ['check_in' => CarbonImmutable::today()->subDay()->toDateString(), 'check_out' => CarbonImmutable::today()->addDay()->toDateString()]);
        $pending = $this->booking('BK-PEND01', null, ['status' => Booking::STATUS_PENDING]);
        $cancelled = $this->booking('BK-CANC01', null, ['status' => Booking::STATUS_CANCELLED]);

        $this->artisan('bookings:complete-stays')->assertSuccessful();

        $this->assertSame(Booking::STATUS_COMPLETED, $over->fresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $tomorrow->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['hotel_id' => $this->hotel->id, 'action' => 'booking.completed', 'actor' => 'system', 'subject_id' => $over->id]);
    }

    public function test_a_completed_stay_cannot_be_reopened_but_can_still_be_cancelled(): void
    {
        $booking = $this->booking('BK-OVER01', null, ['status' => Booking::STATUS_COMPLETED]);
        $this->actingAs($this->owner());

        $this->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame(Booking::STATUS_COMPLETED, $booking->fresh()->status);

        $this->patch(route('admin.bookings.status', $booking), ['status' => 'cancelled'])->assertSessionHas('status');
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);

        $this->get(route('admin.bookings.index', ['status' => 'completed']))->assertOk();
    }

    public function test_a_guest_can_no_longer_change_a_completed_stay_and_sees_it_as_completed(): void
    {
        $this->booking('BK-OVER01', $this->member, ['status' => Booking::STATUS_COMPLETED, 'check_in' => $this->start->toDateString(), 'check_out' => $this->start->addDays(2)->toDateString()]);

        $this->actingAs($this->member, 'member')->post('/demo/member/bookings/BK-OVER01/cancel')->assertSessionHas('error');
        $this->get('/demo/member?lang=en')->assertSee('Stay completed');
    }
}
