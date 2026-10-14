<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class HotelUser extends Pivot
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_FRONT_DESK = 'front_desk';

    public const ROLE_STAFF = 'staff';

    /**
     * What each role may do in the admin. Anything not listed is refused.
     *
     * @var array<string, list<string>>
     */
    public const ABILITIES = [
        self::ROLE_OWNER => ['content', 'inventory', 'bookings.view', 'bookings.manage', 'handovers', 'analytics', 'activity', 'settings', 'team', 'members'],
        self::ROLE_FRONT_DESK => ['inventory', 'bookings.view', 'bookings.manage', 'handovers', 'analytics', 'members'],
        self::ROLE_STAFF => ['bookings.view', 'handovers'],
    ];

    protected $table = 'hotel_users';

    public static function roleCan(?string $role, string $ability): bool
    {
        return in_array($ability, self::ABILITIES[$role] ?? [], true);
    }
}
