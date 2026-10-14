<?php

namespace App\Core\Member\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Whatever a member belongs to: a hotel today, an apartment building or any
 * other FTS AI site tomorrow. The member core only ever talks to this.
 */
interface MemberTenant
{
    /**
     * Whether the member add-on is switched on for this tenant.
     */
    public function supportsMembers(): bool;

    /**
     * The name members see in the sign-in mail and on their account page.
     */
    public function memberBrandName(): string;

    /**
     * The language used until a member picks their own.
     */
    public function memberDefaultLocale(): string;

    /**
     * The address the sign-in mail is answered at, if the tenant has one.
     */
    public function memberReplyTo(): ?string;

    public function members(): MorphMany;
}
