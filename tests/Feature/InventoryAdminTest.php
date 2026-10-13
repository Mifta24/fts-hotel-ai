<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\RoomInventory;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $room;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $this->staff = User::factory()->create();
        $this->hotel->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function night(CarbonImmutable $date): ?RoomInventory
    {
        return RoomInventory::where('room_type_id', $this->room->id)->whereDate('stay_date', $date->toDateString())->first();
    }

    public function test_staff_can_open_nights_for_sale_and_the_calendar_shows_them(): void
    {
        $from = CarbonImmutable::today()->addDays(2);

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['room_type_id' => $this->room->id, 'from' => $from->toDateString(), 'to' => $from->addDays(2)->toDateString(), 'total_units' => 4, 'price' => 1250000])
            ->assertRedirect();

        $this->assertSame(3, RoomInventory::count());
        $this->assertSame(4, $this->night($from)->total_units);
        $this->assertSame(1250000.0, (float) $this->night($from->addDay())->price);

        $this->actingAs($this->staff)
            ->get(route('admin.inventory.index', ['room' => $this->room->id, 'month' => $from->format('Y-m')]))
            ->assertOk()
            ->assertSee('1.250.000');
    }

    public function test_only_the_chosen_weekdays_change_and_booked_rooms_are_never_taken_away(): void
    {
        $monday = CarbonImmutable::today()->next('monday');
        foreach (range(0, 6) as $offset) {
            RoomInventory::create(['room_type_id' => $this->room->id, 'stay_date' => $monday->addDays($offset)->toDateString(), 'total_units' => 5, 'booked_units' => 2, 'price' => 1000000]);
        }

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['room_type_id' => $this->room->id, 'from' => $monday->toDateString(), 'to' => $monday->addDays(6)->toDateString(), 'price' => 1500000, 'weekdays' => [5, 6]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1500000.0, (float) $this->night($monday->addDays(4))->price);
        $this->assertSame(1500000.0, (float) $this->night($monday->addDays(5))->price);
        $this->assertSame(1000000.0, (float) $this->night($monday)->price);

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['room_type_id' => $this->room->id, 'from' => $monday->toDateString(), 'to' => $monday->toDateString(), 'total_units' => 1])
            ->assertSessionHasErrors('total_units');

        $this->assertSame(5, $this->night($monday)->total_units);
    }

    public function test_new_nights_need_both_values_and_staff_cannot_touch_another_hotels_rooms(): void
    {
        $day = CarbonImmutable::today()->addDays(3)->toDateString();

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['room_type_id' => $this->room->id, 'from' => $day, 'to' => $day, 'total_units' => 2])
            ->assertSessionHasErrors('price');

        $other = Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $foreign = $other->roomTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'max_adults' => 1, 'max_children' => 0, 'is_active' => true]);

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update'), ['room_type_id' => $foreign->id, 'from' => $day, 'to' => $day, 'total_units' => 2, 'price' => 1])
            ->assertNotFound();

        $this->assertSame(0, RoomInventory::count());
    }

    public function test_the_extend_command_opens_more_nights_repeating_the_weekly_price_pattern(): void
    {
        $today = CarbonImmutable::today();
        foreach (range(0, 6) as $offset) {
            RoomInventory::create(['room_type_id' => $this->room->id, 'stay_date' => $today->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 2, 'price' => 1000000 + $offset * 10000]);
        }

        $this->artisan('inventory:extend', ['--days' => 20])->assertSuccessful();
        $this->artisan('inventory:extend', ['--days' => 20])->assertSuccessful();

        $this->assertSame(21, RoomInventory::count());
        $later = $this->night($today->addDays(9));
        $this->assertSame(3, $later->total_units);
        $this->assertSame(0, $later->booked_units);
        $this->assertSame(1020000.0, (float) $later->price);
    }
}
