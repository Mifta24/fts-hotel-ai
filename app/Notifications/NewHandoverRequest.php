<?php

namespace App\Notifications;

use App\Models\HandoverRequest;
use App\Notifications\Concerns\SentOnBehalfOfHotel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewHandoverRequest extends Notification implements ShouldQueue
{
    use Queueable, SentOnBehalfOfHotel;

    public function __construct(public HandoverRequest $handover)
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
        return $this->fromHotel((new MailMessage), $this->handover->conversation->hotel)
            ->subject('A guest is waiting for the hotel team')
            ->line('Reason: '.str_replace('_', ' ', $this->handover->reason))
            ->line($this->handover->summary)
            ->action('Open the conversation', route('admin.handovers.show', $this->handover));
    }
}
