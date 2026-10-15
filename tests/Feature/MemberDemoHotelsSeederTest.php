<?php

namespace Tests\Feature;

use App\Core\Member\Models\Member;
use App\Core\Member\Models\MemberPoint;
use App\Models\Booking;
use App\Models\Hotel;
use Database\Seeders\MemberDemoHotelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDemoHotelsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $slug, string $email): Member
    {
        return Hotel::where('slug', $slug)->firstOrFail()->members()->where('email', $email)->firstOrFail();
    }

    private function tierName(string $slug, string $email): ?string
    {
        $hotel = Hotel::where('slug', $slug)->firstOrFail();

        return $this->member($slug, $email)->tierIn($hotel->loyaltySettings())?->name;
    }

    public function test_it_seeds_three_hotels_with_different_member_features(): void
    {
        $this->seed(MemberDemoHotelsSeeder::class);

        $cakrawala = Hotel::where('slug', 'cakrawala-business-hotel')->firstOrFail();
        $kasturi = Hotel::where('slug', 'omah-kasturi')->firstOrFail();
        $kamogawa = Hotel::where('slug', 'kamogawa-machiya-stay')->firstOrFail();

        $this->assertTrue($cakrawala->supportsLoyalty());
        $this->assertTrue($kasturi->supportsMembers());
        $this->assertFalse($kasturi->supportsLoyalty());
        $this->assertTrue($kamogawa->supportsLoyalty());

        foreach ([$cakrawala, $kasturi, $kamogawa] as $hotel) {
            $this->assertTrue($hotel->isPublished());
            $this->assertGreaterThanOrEqual(2, $hotel->roomTypes()->count());
            $this->assertGreaterThanOrEqual(5, $hotel->knowledgeItems()->count());
            $this->assertSame(2, $hotel->users()->count());
        }
    }

    public function test_points_and_tiers_come_from_the_real_award_job(): void
    {
        $this->seed(MemberDemoHotelsSeeder::class);

        $this->assertSame('Platinum', $this->tierName('cakrawala-business-hotel', 'rina.wulandari@example.test'));
        $this->assertSame('Gold', $this->tierName('cakrawala-business-hotel', 'budi.santoso@example.test'));
        $this->assertSame('Silver', $this->tierName('cakrawala-business-hotel', 'maya.pratiwi@example.test'));
        $this->assertNull($this->tierName('cakrawala-business-hotel', 'ahmad.fauzi@example.test'));

        $this->assertSame('Gold', $this->tierName('kamogawa-machiya-stay', 'kenji.sato@example.test'));
        $this->assertSame('Silver', $this->tierName('kamogawa-machiya-stay', 'emi.tanaka@example.test'));
    }

    public function test_a_stay_cancelled_after_paying_out_has_its_points_taken_back(): void
    {
        $this->seed(MemberDemoHotelsSeeder::class);

        $sinta = $this->member('cakrawala-business-hotel', 'sinta.dewi@example.test');

        $reversed = $sinta->points()->where('type', MemberPoint::TYPE_REVERSE)->get();

        $this->assertCount(1, $reversed);
        $this->assertSame(2, $sinta->points()->where('type', MemberPoint::TYPE_EARN)->count());
        $this->assertSame(
            $sinta->points()->where('type', MemberPoint::TYPE_EARN)->firstOrFail()->points,
            $sinta->pointsBalance(),
        );
    }

    public function test_the_hotel_without_points_gives_no_points_or_discounts(): void
    {
        $this->seed(MemberDemoHotelsSeeder::class);

        $hotel = Hotel::where('slug', 'omah-kasturi')->firstOrFail();

        $this->assertSame(0, MemberPoint::whereIn('member_id', $hotel->members()->pluck('id'))->count());
        $this->assertSame(0.0, (float) Booking::where('hotel_id', $hotel->id)->sum('discount_amount'));
        $this->assertGreaterThan(0, Booking::where('hotel_id', $hotel->id)->whereNotNull('member_id')->count());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seed(MemberDemoHotelsSeeder::class);

        $counts = fn () => [Hotel::count(), Member::count(), Booking::count(), MemberPoint::count(), Booking::where('status', Booking::STATUS_CANCELLED)->count()];
        $before = $counts();

        $this->seed(MemberDemoHotelsSeeder::class);

        $this->assertSame($before, $counts());
    }
}
