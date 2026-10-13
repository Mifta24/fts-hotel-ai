<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTestCommandTest extends TestCase
{
    public function test_it_sends_one_test_message_to_the_given_address(): void
    {
        config(['mail.default' => 'array']);

        $this->artisan('mail:test', ['email' => 'staff@example.test'])->assertSuccessful();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);
        $this->assertSame('staff@example.test', $messages[0]->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_it_refuses_an_invalid_address(): void
    {
        $this->artisan('mail:test', ['email' => 'not-an-email'])->assertExitCode(2);
    }
}
