<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'hotel_users')
            ->using(HotelUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * The role this person holds at their current hotel, if any.
     */
    public function currentRole(): ?string
    {
        return $this->currentHotel()?->pivot?->role;
    }

    public function canInHotel(string $ability): bool
    {
        return HotelUser::roleCan($this->currentRole(), $ability);
    }

    /**
     * The hotel this person is working in: the one picked in the admin
     * switcher, otherwise the first hotel they belong to.
     */
    public function currentHotel(): ?Hotel
    {
        $active = $this->hotels()->wherePivot('status', 'active');
        $chosen = session('current_hotel_id');

        if ($chosen && ($hotel = (clone $active)->where('hotels.id', $chosen)->first())) {
            return $hotel;
        }

        return $active->first();
    }
}
