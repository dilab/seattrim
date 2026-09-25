<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to owners when a token refresh fails permanently. */
class ZoomConnectionRevoked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Organization $organization, public string $reason) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[SeatTrim] Zoom connection for {$this->organization->name} needs attention")
            ->greeting('Hello,')
            ->line("SeatTrim could no longer refresh its Zoom access for **{$this->organization->name}**. Zoom said: {$this->reason}")
            ->line('Daily scans and any automation are paused until an admin reconnects. Nothing was changed in your Zoom account.')
            ->action('Reconnect Zoom', route('connection.edit'))
            ->line('This usually happens when the app was removed in the Zoom Marketplace, the installing admin left, or the refresh token expired after 90 days without use.');
    }
}
