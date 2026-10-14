<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Concierge\ConciergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->owner = User::factory()->create();
        $this->hotel->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
    }

    public function test_the_owner_adds_new_and_existing_people_with_a_role(): void
    {
        $existing = User::factory()->create(['email' => 'known@demo.test']);

        $this->actingAs($this->owner)->post(route('admin.team.store'), ['email' => 'new@demo.test', 'name' => 'Rani', 'role' => 'front_desk'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertStringContainsString('temporary password', session('status'));
        $this->actingAs($this->owner)->post(route('admin.team.store'), ['email' => 'known@demo.test', 'role' => 'staff'])->assertSessionHasNoErrors();

        $new = User::where('email', 'new@demo.test')->firstOrFail();
        $this->assertSame('front_desk', $this->hotel->users()->whereKey($new->id)->firstOrFail()->pivot->role);
        $this->assertSame('staff', $this->hotel->users()->whereKey($existing->id)->firstOrFail()->pivot->role);

        $this->actingAs($this->owner)->post(route('admin.team.store'), ['email' => 'known@demo.test', 'role' => 'staff'])->assertSessionHasErrors('email');
    }

    public function test_changing_a_role_changes_what_the_person_can_open(): void
    {
        $member = User::factory()->create();
        $this->hotel->users()->attach($member->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($member)->get(route('admin.inventory.index'))->assertForbidden();

        $this->actingAs($this->owner)->put(route('admin.team.update', $member), ['role' => 'front_desk', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->actingAs($member)->get(route('admin.inventory.index'))->assertOk();

        $this->actingAs($this->owner)->put(route('admin.team.update', $member), ['role' => 'front_desk', 'status' => 'suspended']);
        $this->actingAs($member)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_the_hotel_always_keeps_an_active_owner(): void
    {
        $this->actingAs($this->owner)->put(route('admin.team.update', $this->owner), ['role' => 'staff', 'status' => 'active'])->assertSessionHasErrors('role');
        $this->actingAs($this->owner)->put(route('admin.team.update', $this->owner), ['role' => 'owner', 'status' => 'suspended'])->assertSessionHasErrors('role');
        $this->actingAs($this->owner)->delete(route('admin.team.destroy', $this->owner))->assertSessionHasErrors('role');

        $second = User::factory()->create();
        $this->hotel->users()->attach($second->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($this->owner)->put(route('admin.team.update', $this->owner), ['role' => 'staff', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('staff', $this->hotel->users()->whereKey($this->owner->id)->firstOrFail()->pivot->role);
    }

    public function test_only_owners_manage_the_team_and_only_their_own_hotels_members(): void
    {
        $staff = User::factory()->create();
        $this->hotel->users()->attach($staff->id, ['role' => 'front_desk', 'status' => 'active']);
        $other = Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($staff)->get(route('admin.team.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.team.store'), ['email' => 'x@demo.test', 'role' => 'owner'])->assertForbidden();
        $this->actingAs($this->owner)->put(route('admin.team.update', $stranger), ['role' => 'owner', 'status' => 'active'])->assertNotFound();
        $this->actingAs($this->owner)->delete(route('admin.team.destroy', $stranger))->assertNotFound();
    }

    public function test_anyone_can_change_their_own_password_but_only_with_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        $this->hotel->users()->attach($user->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($user)->put(route('admin.account.update'), ['current_password' => 'wrong', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put(route('admin.account.update'), ['current_password' => 'old-password-123', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($user)->put(route('admin.account.update'), ['current_password' => 'old-password-123', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass-1', $user->fresh()->password));
    }

    public function test_each_hotel_can_give_its_concierge_its_own_model_and_guidance(): void
    {
        config(['services.local_llm.base_url' => 'http://llm.test', 'services.local_llm.api_key' => 'k', 'services.local_llm.model' => 'default-model']);
        $this->hotel->update(['settings' => ['ai_model' => 'hotel-model', 'ai_instructions' => 'Always greet guests with "Konnichiwa".']]);
        $plain = Hotel::create(['name' => 'Plain', 'slug' => 'plain', 'public_status' => 'published']);
        Http::fake(['llm.test/*' => Http::response(['choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']]])]);

        foreach ([$this->hotel, $plain] as $hotel) {
            $conversation = Conversation::create(['hotel_id' => $hotel->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);
            app(ConciergeService::class)->reply($hotel, $conversation, 'Hello');
        }

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]->data());
        $this->assertSame('hotel-model', $sent[0]['model']);
        $this->assertStringContainsString('Konnichiwa', $sent[0]['messages'][0]['content']);
        $this->assertStringContainsString('never overrides the hard rules', $sent[0]['messages'][0]['content']);
        $this->assertSame('default-model', $sent[1]['model']);
        $this->assertStringNotContainsString('Guidance from', $sent[1]['messages'][0]['content']);
        $this->assertSame(2, ConversationMessage::where('role', 'assistant')->count());
    }

    public function test_platform_admins_set_the_model_of_a_hotel(): void
    {
        $admin = User::factory()->create();
        $admin->is_platform_admin = true;
        $admin->save();

        $this->actingAs($this->owner)->patch(route('platform.hotels.ai-model', $this->hotel), ['ai_model' => 'x'])->assertForbidden();
        $this->actingAs($admin)->patch(route('platform.hotels.ai-model', $this->hotel), ['ai_model' => 'qwen3.6-35b'])->assertSessionHasNoErrors();
        $this->assertSame('qwen3.6-35b', $this->hotel->fresh()->setting('ai_model'));

        $this->actingAs($admin)->patch(route('platform.hotels.ai-model', $this->hotel), ['ai_model' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->hotel->fresh()->setting('ai_model'));
    }
}
