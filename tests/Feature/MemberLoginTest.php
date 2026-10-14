<?php

namespace Tests\Feature;

use App\Core\Member\Mail\MemberLoginCodeMail;
use App\Core\Member\Models\Member;
use App\Core\Member\Models\MemberLoginCode;
use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::factory()->withMembers()->create(['slug' => 'demo']);
    }

    private function requestCode(string $email = 'ayu@example.test', ?Hotel $hotel = null): string
    {
        Mail::fake();

        $this->post(route('member.login.send', ['hotelSlug' => ($hotel ?? $this->hotel)->slug]), ['email' => $email])
            ->assertRedirect(route('member.verify', ['hotelSlug' => ($hotel ?? $this->hotel)->slug]));

        $sent = Mail::sent(MemberLoginCodeMail::class);
        $this->assertCount(1, $sent);

        return $sent->first()->code;
    }

    private function submitCode(string $code, string $email = 'ayu@example.test', ?Hotel $hotel = null)
    {
        return $this->post(route('member.verify.check', ['hotelSlug' => ($hotel ?? $this->hotel)->slug]), ['email' => $email, 'code' => $code]);
    }

    public function test_the_member_pages_exist_only_for_a_published_hotel_with_the_add_on(): void
    {
        $this->get(route('member.login', ['hotelSlug' => 'demo']))->assertOk();

        $plain = Hotel::factory()->create(['slug' => 'plain']);
        $draft = Hotel::factory()->withMembers()->draft()->create(['slug' => 'hidden']);

        $this->get(route('member.login', ['hotelSlug' => $plain->slug]))->assertNotFound();
        $this->post(route('member.login.send', ['hotelSlug' => $plain->slug]), ['email' => 'a@example.test'])->assertNotFound();
        $this->get(route('member.login', ['hotelSlug' => $draft->slug]))->assertNotFound();
        $this->get(route('member.account', ['hotelSlug' => $plain->slug]))->assertNotFound();
    }

    public function test_asking_for_a_code_mails_it_without_creating_an_account(): void
    {
        $code = $this->requestCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(0, Member::count());
        $this->assertDatabaseMissing('member_login_codes', ['code_hash' => $code]);
        Mail::assertSent(MemberLoginCodeMail::class, fn (MemberLoginCodeMail $mail) => $mail->hasTo('ayu@example.test') && $mail->tenant->is($this->hotel));
    }

    public function test_the_right_code_signs_the_guest_in_and_creates_the_member_once(): void
    {
        $code = $this->requestCode('Ayu@Example.test ');

        $this->submitCode($code, 'Ayu@Example.test ')->assertRedirect(route('member.account', ['hotelSlug' => 'demo']));

        $member = Member::firstOrFail();
        $this->assertSame('ayu@example.test', $member->email);
        $this->assertTrue($member->belongsToTenant($this->hotel));
        $this->assertNotNull($member->email_verified_at);
        $this->assertAuthenticatedAs($member, 'member');

        $this->get(route('member.account', ['hotelSlug' => 'demo']))->assertOk()->assertSee($member->email);
    }

    public function test_a_returning_member_signs_in_to_the_same_account(): void
    {
        $existing = Member::factory()->forHotel($this->hotel)->create(['email' => 'ayu@example.test', 'name' => 'Ayu']);

        $this->submitCode($this->requestCode())->assertRedirect();

        $this->assertSame(1, Member::count());
        $this->assertAuthenticatedAs($existing, 'member');
    }

    public function test_a_code_works_only_once(): void
    {
        $code = $this->requestCode();
        $this->submitCode($code)->assertRedirect(route('member.account', ['hotelSlug' => 'demo']));

        $this->post(route('member.logout', ['hotelSlug' => 'demo']))->assertRedirect();
        $this->assertGuest('member');

        $this->submitCode($code)->assertSessionHasErrors('code');
        $this->assertGuest('member');
    }

    public function test_a_wrong_code_is_refused_and_too_many_wrong_tries_burn_the_code(): void
    {
        $code = $this->requestCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, MemberLoginCode::MAX_ATTEMPTS) as $try) {
            $this->submitCode($wrong)->assertSessionHasErrors('code');
        }

        $this->submitCode($code)->assertSessionHasErrors('code');
        $this->assertGuest('member');
        $this->assertSame(0, Member::count());
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->requestCode();

        $this->travel(11)->minutes();

        $this->submitCode($code)->assertSessionHasErrors('code');
        $this->assertGuest('member');
    }

    public function test_a_code_is_not_valid_for_another_email_or_hotel(): void
    {
        $code = $this->requestCode();
        $other = Hotel::factory()->withMembers()->create(['slug' => 'other']);

        $this->submitCode($code, 'someone@example.test')->assertSessionHasErrors('code');
        $this->submitCode($code, 'ayu@example.test', $other)->assertSessionHasErrors('code');
        $this->assertGuest('member');
    }

    public function test_a_second_request_within_a_minute_sends_no_new_mail(): void
    {
        $this->requestCode();
        Mail::fake();

        $this->post(route('member.login.send', ['hotelSlug' => 'demo']), ['email' => 'ayu@example.test'])->assertRedirect()->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_a_new_code_replaces_the_old_one(): void
    {
        $first = $this->requestCode();

        $this->travel(2)->minutes();
        Mail::fake();
        $this->post(route('member.login.send', ['hotelSlug' => 'demo']), ['email' => 'ayu@example.test'])->assertRedirect();
        $second = Mail::sent(MemberLoginCodeMail::class)->first()->code;

        $this->submitCode($first === $second ? '000000' : $first)->assertSessionHasErrors('code');
        $this->submitCode($second)->assertRedirect(route('member.account', ['hotelSlug' => 'demo']));
    }

    public function test_an_invalid_email_is_refused_and_nothing_is_mailed(): void
    {
        Mail::fake();

        $this->post(route('member.login.send', ['hotelSlug' => 'demo']), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_the_sign_in_mail_shows_the_code_under_the_hotel_name_in_the_guests_language(): void
    {
        $this->hotel->update(['name' => 'Sakura Inn', 'email' => 'front@sakura.test']);

        $subjects = ['en' => 'Your sign-in code for Sakura Inn', 'id' => 'Kode masuk Anda untuk Sakura Inn', 'ja' => 'Sakura Inn のログインコード'];

        foreach ($subjects as $locale => $subject) {
            $mail = new MemberLoginCodeMail($this->hotel->fresh(), '482913', $locale);

            $mail->assertHasSubject($subject);
            $mail->assertSeeInHtml('482913');
            $mail->assertSeeInText('482913');
            $mail->assertFrom(config('mail.from.address'), 'Sakura Inn');
            $mail->assertHasReplyTo('front@sakura.test');
        }
    }

    public function test_the_account_page_needs_a_member_of_that_same_hotel(): void
    {
        $other = Hotel::factory()->withMembers()->create(['slug' => 'other']);
        $member = Member::factory()->forHotel($other)->create();

        $this->get(route('member.account', ['hotelSlug' => 'demo']))->assertRedirect(route('member.login', ['hotelSlug' => 'demo']));

        $this->actingAs($member, 'member')->get(route('member.account', ['hotelSlug' => 'demo']))->assertRedirect(route('member.login', ['hotelSlug' => 'demo']));
        $this->actingAs($member, 'member')->get(route('member.account', ['hotelSlug' => 'other']))->assertOk();
    }

    public function test_signing_in_claims_earlier_bookings_made_with_the_same_email_only(): void
    {
        $room = $this->hotel->roomTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);
        $otherHotel = Hotel::factory()->withMembers()->create(['slug' => 'other']);
        $otherRoom = $otherHotel->roomTypes()->create(['name' => 'Suite', 'slug' => 'suite', 'base_price' => 1000000, 'max_adults' => 2, 'max_children' => 0, 'is_active' => true]);

        $make = fn (Hotel $hotel, $roomType, string $reference, ?string $email, ?string $phone = null) => Booking::create([
            'reference' => $reference, 'hotel_id' => $hotel->id, 'room_type_id' => $roomType->id, 'guest_name' => 'Ayu', 'guest_email' => $email, 'guest_phone' => $phone,
            'room_count' => 1, 'adults' => 2, 'status' => Booking::STATUS_PENDING, 'check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString(), 'total_price' => 2000000,
        ]);

        $mine = $make($this->hotel, $room, 'BK-MINE01', 'AYU@example.test');
        $stranger = $make($this->hotel, $room, 'BK-OTHER1', 'stranger@example.test');
        $byPhone = $make($this->hotel, $room, 'BK-PHONE1', null, '+62 811 1112 223');
        $elsewhere = $make($otherHotel, $otherRoom, 'BK-ELSE01', 'ayu@example.test');

        $this->submitCode($this->requestCode())->assertRedirect();

        $member = Member::firstOrFail();
        $this->assertSame($member->id, $mine->fresh()->member_id);
        $this->assertNull($stranger->fresh()->member_id);
        $this->assertNull($byPhone->fresh()->member_id);
        $this->assertNull($elsewhere->fresh()->member_id);
    }

    public function test_old_login_codes_are_cleared_away_and_recent_ones_are_kept(): void
    {
        $make = fn (string $email, $expiresAt) => $this->hotel->morphMany(MemberLoginCode::class, 'tenant')->create(['email' => $email, 'code_hash' => str_repeat('a', 64), 'expires_at' => $expiresAt]);

        $make('old@example.test', now()->subDays(2));
        $make('recent@example.test', now()->subHour());

        $this->artisan('model:prune', ['--model' => [MemberLoginCode::class]])->assertSuccessful();

        $this->assertDatabaseMissing('member_login_codes', ['email' => 'old@example.test']);
        $this->assertDatabaseHas('member_login_codes', ['email' => 'recent@example.test']);
    }
}
