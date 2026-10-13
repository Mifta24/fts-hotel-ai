<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking)
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
        $contact = $booking->guest_phone ?? $booking->guest_email;

        return (new MailMessage)
            ->subject("New reservation request {$booking->reference}")
            ->line("{$booking->guest_name} asked to book {$booking->room_count} × {$booking->roomType->name}.")
            ->line("Stay: {$booking->check_in->toFormattedDateString()} → {$booking->check_out->toFormattedDateString()} · {$booking->adults} adults".($booking->children ? ", {$booking->children} children" : ''))
            ->line("Contact ({$booking->contact_type}): {$contact}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Request: {$booking->notes}"))
            ->action('Review bookings', route('admin.bookings.index', ['status' => Booking::STATUS_PENDING]));
    }
}
