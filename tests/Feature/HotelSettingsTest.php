<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\User;
use App\Notifications\NewBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HotelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.url' => 'http://platform.test']);
        $this->hotel = Hotel::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'id']);
        $this->owner = User::factory()->create();
        $this->hotel->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function form(array $override = []): array
    {
        return [
            'name' => 'Demo Resort', 'timezone' => 'Asia/Tokyo', 'currency' => 'jpy', 'default_locale' => 'ja', 'check_in_time' => '15:00', 'check_out_time' => '11:00',
            'public_status' => 'published', 'phone' => '+81 3 1234 5678', 'whatsapp' => '+81 90 1111 2222', 'email' => 'front@demo.test', ...$override,
        ];
    }

    public function test_the_owner_edits_the_profile_look_and_publishing_of_the_hotel(): void
    {
        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form([
            'logo' => UploadedFile::fake()->image('logo.png', 600, 300),
            'cover' => UploadedFile::fake()->image('cover.jpg', 2400, 1200),
            'ai_instructions' => 'Speak formally.',
            'public_status' => 'draft',
        ]))->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors();

        $hotel = $this->hotel->fresh();
        $this->assertSame('Demo Resort', $hotel->name);
        $this->assertSame('JPY', $hotel->currency);
        $this->assertStringStartsWith('15:00', $hotel->check_in_time);
        $this->assertSame('draft', $hotel->public_status);
        $this->assertSame('Speak formally.', $hotel->setting('ai_instructions'));
        Storage::disk('public')->assertExists($hotel->logo_path);
        Storage::disk('public')->assertExists($hotel->cover_path);
        $this->assertDatabaseHas('audit_logs', ['hotel_id' => $hotel->id, 'action' => 'hotel.settings']);

        $oldLogo = $hotel->logo_path;
        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['remove_logo' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($hotel->fresh()->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
    }

    public function test_only_owners_open_the_settings_and_the_logo_shows_on_the_hotel_pages(): void
    {
        $frontDesk = User::factory()->create();
        $this->hotel->users()->attach($frontDesk->id, ['role' => 'front_desk', 'status' => 'active']);

        $this->actingAs($frontDesk)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($frontDesk)->put(route('admin.settings.update'), $this->form())->assertForbidden();

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['logo' => UploadedFile::fake()->image('logo.png', 200, 200), 'cover' => UploadedFile::fake()->image('c.jpg', 800, 400)]));
        $logo = $this->hotel->fresh()->logoUrl();

        $this->get('/demo')->assertOk()->assertSee($logo, false)->assertSee('og:image', false);
    }

    public function test_the_domain_must_look_like_a_domain_and_belong_to_one_hotel_only(): void
    {
        Hotel::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published', 'custom_domain' => 'other.example.com']);

        foreach (['https://hotel.example.com', 'hotel', 'hotel.example.com/path', 'other.example.com', 'platform.test'] as $bad) {
            $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => $bad]))->assertSessionHasErrors('custom_domain');
        }

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => 'Stay.Demo.Example.com']))->assertSessionHasNoErrors();
        $this->assertSame('stay.demo.example.com', $this->hotel->fresh()->custom_domain);
    }

    public function test_a_hotels_own_domain_serves_the_hotel_from_its_root_with_slug_free_links(): void
    {
        $this->hotel->update(['custom_domain' => 'stay.demo.example.com']);
        $this->hotel->roomTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $base = 'http://stay.demo.example.com';

        $this->get($base.'/')->assertOk()->assertSee($base.'/rooms', false)->assertDontSee('/demo/rooms', false);
        $this->get($base.'/rooms')->assertOk()->assertSee('Deluxe King');
        $this->get($base.'/rooms/deluxe-king')->assertOk();
        $this->get($base.'/admin/login')->assertOk();

        $this->get('http://unknown.example.com/rooms')->assertNotFound();
        $this->get('http://platform.test/demo/rooms')->assertOk();
    }

    public function test_guest_actions_work_on_the_hotels_own_domain(): void
    {
        $this->hotel->update(['custom_domain' => 'stay.demo.example.com']);
        Http::fake();

        $this->postJson('http://stay.demo.example.com/concierge/start', ['locale' => 'en'])->assertOk()->assertJsonStructure(['guest_token']);
    }

    public function test_a_changed_domain_is_picked_up_without_waiting_for_the_cache(): void
    {
        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => 'one.example.com']))->assertSessionHasNoErrors();
        $this->get('http://one.example.com/rooms')->assertOk();
        $this->assertNotNull(Cache::get('custom-domain:one.example.com'));

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => 'two.example.com']))->assertSessionHasNoErrors();
        $this->get('http://one.example.com/rooms')->assertNotFound();
        $this->get('http://two.example.com/rooms')->assertOk();
    }

    public function test_staff_mail_comes_from_the_hotel_name_and_replies_go_to_the_hotel(): void
    {
        $this->hotel->update(['email' => 'front@demo.test']);
        $room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $booking = Booking::create([
            'reference' => 'BK-MAIL9', 'hotel_id' => $this->hotel->id, 'room_type_id' => $room->id, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811', 'contact_type' => 'phone', 'room_count' => 1,
            'check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);

        $mail = (new NewBookingRequest($booking))->toMail($this->owner);

        $this->assertSame('Demo', $mail->from[1]);
        $this->assertSame('front@demo.test', $mail->replyTo[0][0]);
    }
}
