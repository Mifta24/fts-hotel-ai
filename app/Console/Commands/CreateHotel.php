<?php

namespace App\Console\Commands;

use App\Services\HotelOnboarding;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hotel:create {name : Name of the hotel} {--owner-email= : Email of the owner (an account is created if it does not exist)} {--owner-name=} {--slug=} {--city=} {--country=} {--currency=IDR} {--locale=id}')]
#[Description('Open a new hotel on the platform with its owner account; the hotel stays hidden until published')]
class CreateHotel extends Command
{
    public function handle(HotelOnboarding $onboarding): int
    {
        if (! filter_var($this->option('owner-email'), FILTER_VALIDATE_EMAIL)) {
            $this->error('Give the owner with --owner-email=someone@example.com');

            return self::INVALID;
        }

        $result = $onboarding->create([
            'name' => $this->argument('name'),
            'slug' => $this->option('slug') ?: null,
            'city' => $this->option('city') ?: null,
            'country' => $this->option('country') ?: null,
            'currency' => $this->option('currency'),
            'default_locale' => $this->option('locale'),
        ], $this->option('owner-email'), $this->option('owner-name') ?: null);

        $this->info("Created \"{$result['hotel']->name}\" at /{$result['hotel']->slug} (draft).");

        if ($result['password']) {
            $this->line("New owner account {$result['owner']->email} — temporary password: {$result['password']}");
        } else {
            $this->line("{$result['owner']->email} already had an account and is now the owner.");
        }

        return self::SUCCESS;
    }
}
