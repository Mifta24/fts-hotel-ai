<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('mail:test {email : Where to send the test message}')]
#[Description('Send one test email with the current mail settings, to check the SMTP setup')]
class SendTestMail extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not a valid email address.');

            return self::INVALID;
        }

        $this->line('Mailer: '.config('mail.default').' · '.config('mail.mailers.'.config('mail.default').'.host').':'.config('mail.mailers.'.config('mail.default').'.port'));

        try {
            Mail::raw('This is a test from '.config('app.name').'. If you can read it, the hotel can email its staff.', function ($message) use ($email): void {
                $message->to($email)->subject('Test email — '.config('app.name'));
            });
        } catch (\Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Test email sent to {$email}.");

        return self::SUCCESS;
    }
}
