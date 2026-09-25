<?php

namespace App\Notifications;

use App\Actions\Zoom\DisconnectZoom;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to owners after a deauthorization or manual disconnect, once data is deleted. */
class ZoomDisconnected extends Notification implements ShouldQueue
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
            ->subject("[SeatTrim] Zoom disconnected from {$this->organization->name}")
            ->greeting('Hello,')
            ->line("SeatTrim was {$this->describe()} for **{$this->organization->name}**.")
            ->line('All Zoom-derived data for this organization has been deleted: the connection tokens, user inventory, scans and per-user audit entries. Your SeatTrim account, members and billing records remain.')
            ->action('Open SeatTrim', route('connection.edit'))
            ->line('You can reconnect at any time. The first scan after reconnecting rebuilds the report from scratch.');
    }

    private function describe(): string
    {
        return DisconnectZoom::describeReason($this->reason);
    }
}
