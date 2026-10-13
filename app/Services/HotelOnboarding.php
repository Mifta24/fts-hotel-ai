<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens a new hotel on the platform: the hotel itself (hidden from guests
 * until its owner publishes it) and the owner account that will fill it in.
 */
class HotelOnboarding
{
    /**
     * Slugs that already mean something else in the address bar.
     */
    public const RESERVED_SLUGS = ['up', 'admin', 'platform', 'narration', 'storage', 'build', 'images', 'api'];

    /**
     * @param  array{name: string, slug?: ?string, city?: ?string, country?: ?string, currency?: ?string, default_locale?: ?string, timezone?: ?string}  $hotel
     * @return array{hotel: Hotel, owner: User, password: ?string} the password is set only when a new account had to be created
     */
    public function create(array $hotel, string $ownerEmail, ?string $ownerName = null): array
    {
        return DB::transaction(function () use ($hotel, $ownerEmail, $ownerName): array {
            $created = Hotel::create([
                'name' => $hotel['name'],
                'slug' => $this->uniqueSlug($hotel['slug'] ?? $hotel['name']),
                'city' => $hotel['city'] ?? null,
                'country' => $hotel['country'] ?? null,
                'currency' => strtoupper($hotel['currency'] ?? 'IDR'),
                'default_locale' => $hotel['default_locale'] ?? 'id',
                'timezone' => $hotel['timezone'] ?? 'Asia/Jakarta',
                'public_status' => 'draft',
            ]);

            $owner = User::where('email', $ownerEmail)->first();
            $password = null;

            if (! $owner) {
                $password = Str::password(14, symbols: false);
                $owner = User::create(['name' => $ownerName ?: Str::headline(Str::before($ownerEmail, '@')), 'email' => $ownerEmail, 'password' => $password]);
            }

            $created->users()->syncWithoutDetaching([$owner->id => ['role' => HotelUser::ROLE_OWNER, 'status' => 'active']]);

            AuditLog::record($created->id, 'hotel.created', "Hotel {$created->name} was opened on the platform", $created, null, ['owner' => $owner->email]);

            return ['hotel' => $created, 'owner' => $owner, 'password' => $password];
        });
    }

    public function uniqueSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'hotel';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true) || Hotel::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
