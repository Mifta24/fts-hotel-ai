<?php

namespace App\Notifications\Concerns;

use App\Models\Hotel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail to a hotel's team arrives under that hotel's name, and a reply goes to
 * the hotel's own address — the sending account is shared, the identity is not.
 */
trait SentOnBehalfOfHotel
{
    protected function fromHotel(MailMessage $mail, Hotel $hotel): MailMessage
    {
        $mail->from((string) config('mail.from.address'), $hotel->name);

        return filled($hotel->email) ? $mail->replyTo($hotel->email, $hotel->name) : $mail;
    }
}
