<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestChangedBooking extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'rescheduled'|'cancelled'  $change
     */
    public function __construct(public Booking $booking, public string $change)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $stay = "{$booking->check_in->toFormattedDateString()} → {$booking->check_out->toFormattedDateString()}";

        return (new MailMessage)
            ->subject("Guest {$this->change} booking {$booking->reference}")
            ->line($this->change === 'cancelled'
                ? "{$booking->guest_name} cancelled booking {$booking->reference}. The rooms are free again."
                : "{$booking->guest_name} moved booking {$booking->reference} to {$stay}. It is waiting for your confirmation again.")
            ->action('Open the booking', route('admin.bookings.show', $booking));
    }
}
