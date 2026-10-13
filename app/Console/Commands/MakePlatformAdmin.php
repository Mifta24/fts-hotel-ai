<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:admin {email : Email of an existing user} {--revoke : Take platform access away instead}')]
#[Description('Let a user open and publish hotels for the whole platform')]
class MakePlatformAdmin extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->is_platform_admin = ! $this->option('revoke');
        $user->save();

        $this->info($user->is_platform_admin ? "{$user->email} is now a platform admin." : "{$user->email} is no longer a platform admin.");

        return self::SUCCESS;
    }
}
