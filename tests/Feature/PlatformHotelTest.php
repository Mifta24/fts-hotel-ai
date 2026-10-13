<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformHotelTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->is_platform_admin = true;
        $user->save();

        return $user;
    }

    private function form(array $override = []): array
    {
        return ['name' => 'Sakura Inn', 'currency' => 'jpy', 'default_locale' => 'ja', 'owner_email' => 'owner@sakura.test', 'owner_name' => 'Hana', ...$override];
    }

    public function test_only_platform_admins_open_the_platform_screens(): void
    {
        $this->get(route('platform.hotels.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('platform.hotels.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('platform.hotels.store'), $this->form())->assertForbidden();
        $this->actingAs($this->platformAdmin())->get(route('platform.hotels.index'))->assertOk();
    }

    public function test_a_new_hotel_starts_hidden_with_its_owner_and_is_published_later(): void
    {
        $this->actingAs($this->platformAdmin())->post(route('platform.hotels.store'), $this->form())->assertRedirect()->assertSessionHas('status');

        $hotel = Hotel::where('slug', 'sakura-inn')->firstOrFail();
        $owner = User::where('email', 'owner@sakura.test')->firstOrFail();

        $this->assertSame('draft', $hotel->public_status);
        $this->assertSame('JPY', $hotel->currency);
        $this->assertSame('owner', $hotel->users()->firstOrFail()->pivot->role);
        $this->assertSame($hotel->id, $owner->currentHotel()->id);
        $this->assertDatabaseHas('audit_logs', ['hotel_id' => $hotel->id, 'action' => 'hotel.created']);

        $this->get('/sakura-inn')->assertNotFound();

        $this->actingAs($this->platformAdmin())->patch(route('platform.hotels.status', $hotel), ['public_status' => 'published'])->assertRedirect();
        $this->get('/sakura-inn')->assertOk();
    }

    public function test_reserved_and_taken_addresses_are_refused(): void
    {
        $admin = $this->platformAdmin();
        Hotel::create(['name' => 'Taken', 'slug' => 'taken', 'public_status' => 'published']);

        $this->actingAs($admin)->post(route('platform.hotels.store'), $this->form(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('platform.hotels.store'), $this->form(['slug' => 'taken']))->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('platform.hotels.store'), $this->form(['slug' => 'Bad Slug!']))->assertSessionHasErrors('slug');
    }

    public function test_an_existing_account_becomes_owner_without_a_new_password(): void
    {
        $existing = User::factory()->create(['email' => 'owner@sakura.test']);
        $before = $existing->password;

        $this->artisan('hotel:create', ['name' => 'Sakura Inn', '--owner-email' => 'owner@sakura.test', '--city' => 'Kyoto'])->assertSuccessful();

        $this->assertSame($before, $existing->fresh()->password);
        $this->assertSame('owner', Hotel::firstOrFail()->users()->firstOrFail()->pivot->role);
        $this->assertSame(1, User::count());
    }

    public function test_the_same_name_gets_its_own_address(): void
    {
        $this->artisan('hotel:create', ['name' => 'Sakura Inn', '--owner-email' => 'a@x.test'])->assertSuccessful();
        $this->artisan('hotel:create', ['name' => 'Sakura Inn', '--owner-email' => 'b@x.test'])->assertSuccessful();

        $this->assertEqualsCanonicalizing(['sakura-inn', 'sakura-inn-2'], Hotel::pluck('slug')->all());
    }

    public function test_someone_in_two_hotels_can_switch_between_them_but_not_into_a_third(): void
    {
        $first = Hotel::create(['name' => 'First', 'slug' => 'first', 'public_status' => 'published']);
        $second = Hotel::create(['name' => 'Second', 'slug' => 'second', 'public_status' => 'published']);
        $third = Hotel::create(['name' => 'Third', 'slug' => 'third', 'public_status' => 'published']);
        $user = User::factory()->create();
        $first->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);
        $second->users()->attach($user->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee('Switch hotel', false);

        $this->actingAs($user)->post(route('admin.switch-hotel'), ['hotel_id' => $second->id])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($second->id, $user->currentHotel()->id);
        $this->assertSame('staff', $user->currentRole());

        $this->actingAs($user)->post(route('admin.switch-hotel'), ['hotel_id' => $third->id])->assertForbidden();
    }

    public function test_the_platform_command_grants_and_revokes_access(): void
    {
        $user = User::factory()->create();

        $this->artisan('platform:admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_platform_admin);

        $this->artisan('platform:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_platform_admin);
    }
}
