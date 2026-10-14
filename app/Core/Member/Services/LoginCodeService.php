<?php

namespace App\Core\Member\Services;

use App\Core\Member\Contracts\MemberTenant;
use App\Core\Member\Mail\MemberLoginCodeMail;
use App\Core\Member\Models\Member;
use App\Core\Member\Models\MemberLoginCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Passwordless sign-in: mails a one-time code, then turns the right code into
 * a member. Asking for a code never reveals whether the email already has an
 * account; the account is created only when the code is proven.
 */
class LoginCodeService
{
    public const TTL_MINUTES = 10;

    public const RESEND_AFTER_SECONDS = 60;

    /**
     * Mails a fresh code. Returns false, sending nothing, if one went out a moment ago.
     */
    public function issue(MemberTenant&Model $tenant, string $email, string $locale): bool
    {
        $email = Member::normalizeEmail($email);

        $latest = $this->codesFor($tenant, $email)->latest('id')->first();

        if ($latest && $latest->created_at->gt(now()->subSeconds(self::RESEND_AFTER_SECONDS))) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($tenant, $email, $code): void {
            $this->codesFor($tenant, $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);

            $tenant->morphMany(MemberLoginCode::class, 'tenant')->create([
                'email' => $email,
                'code_hash' => $this->hash($tenant, $email, $code),
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });

        Mail::to($email)->send(new MemberLoginCodeMail($tenant, $code, $locale));

        return true;
    }

    /**
     * The member the code belongs to — created on first sign-in — or null when
     * the code is wrong, used, expired or has been tried too often.
     */
    public function verify(MemberTenant&Model $tenant, string $email, string $code, string $locale): ?Member
    {
        $email = Member::normalizeEmail($email);

        return DB::transaction(function () use ($tenant, $email, $code, $locale): ?Member {
            $row = $this->codesFor($tenant, $email)->whereNull('consumed_at')->latest('id')->lockForUpdate()->first();

            if (! $row || ! $row->isUsable()) {
                return null;
            }

            if (! hash_equals($row->code_hash, $this->hash($tenant, $email, trim($code)))) {
                $row->increment('attempts');

                return null;
            }

            $row->update(['consumed_at' => now()]);

            $member = $tenant->members()->firstOrNew(['email' => $email]);
            $member->locale ??= $locale;
            $member->email_verified_at ??= now();
            $member->last_login_at = now();
            $member->save();

            return $member;
        });
    }

    /**
     * @return Builder<MemberLoginCode>
     */
    private function codesFor(Model $tenant, string $email): Builder
    {
        return MemberLoginCode::where('tenant_type', $tenant->getMorphClass())
            ->where('tenant_id', $tenant->getKey())
            ->where('email', $email);
    }

    private function hash(Model $tenant, string $email, string $code): string
    {
        return hash_hmac('sha256', implode('|', [$tenant->getMorphClass(), $tenant->getKey(), $email, $code]), (string) config('app.key'));
    }
}
