<?php

namespace App\Core\Member\Models;

use App\Core\Member\Contracts\MemberTenant;
use App\Models\InternalNote;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A guest who signed in with a one-time code. There is no password: the
 * mailbox is the proof. A member belongs to exactly one tenant.
 */
#[Fillable(['name', 'email', 'phone', 'locale'])]
#[Hidden(['remember_token'])]
#[UseFactory(MemberFactory::class)]
class Member extends Authenticatable
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    public const GUARD = 'member';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function internalNotes(): MorphMany
    {
        return $this->morphMany(InternalNote::class, 'notable')->latest();
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function displayName(): string
    {
        return filled($this->name) ? $this->name : $this->email;
    }

    public function needsProfile(): bool
    {
        return blank($this->name);
    }

    public function belongsToTenant(MemberTenant&Model $tenant): bool
    {
        return $this->tenant_type === $tenant->getMorphClass() && (int) $this->tenant_id === (int) $tenant->getKey();
    }
}
