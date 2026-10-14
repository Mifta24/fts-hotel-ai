<?php

namespace App\Models;

use App\Core\Member\Contracts\MemberTenant;
use App\Core\Member\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'description',
    'translations',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'phone',
    'whatsapp',
    'email',
    'timezone',
    'currency',
    'default_locale',
    'check_in_time',
    'check_out_time',
    'logo_path',
    'cover_path',
    'public_status',
    'settings',
    'features',
])]
class Hotel extends Model implements MemberTenant
{
    use HasFactory, SoftDeletes;

    /**
     * Optional add-ons a hotel can be given; the platform admin switches them on.
     */
    public const FEATURE_MEMBER = 'member';

    public const FEATURES = [self::FEATURE_MEMBER];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'settings' => 'array',
            'features' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hotel_users')
            ->using(HotelUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * The people who answer for this hotel in the admin, and are told when a guest needs them.
     */
    public function notifiableStaff(): Collection
    {
        return $this->users()->wherePivot('status', 'active')->get();
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(HotelKnowledgeItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function members(): MorphMany
    {
        return $this->morphMany(Member::class, 'tenant');
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function supportsMembers(): bool
    {
        return $this->hasFeature(self::FEATURE_MEMBER);
    }

    public function memberBrandName(): string
    {
        return $this->name;
    }

    public function memberDefaultLocale(): string
    {
        return $this->default_locale;
    }

    public function memberReplyTo(): ?string
    {
        return filled($this->email) ? $this->email : null;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }

    /**
     * A per-hotel option kept in the settings column (ai_model, ai_instructions).
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $value = $this->settings[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    public function logoUrl(): ?string
    {
        return filled($this->logo_path) ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return filled($this->cover_path) ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function isPublished(): bool
    {
        return $this->public_status === 'published';
    }

    public function publicUrl(): string
    {
        return $this->custom_domain
            ? 'https://'.$this->custom_domain
            : url('/'.$this->slug);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hotel';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function reservedSlugs(): array
    {
        return [
            'admin', 'login', 'logout', 'register', 'pricing', 'dashboard',
            'api', 'terms', 'privacy', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'storage', 'build', 'account',
        ];
    }
}
