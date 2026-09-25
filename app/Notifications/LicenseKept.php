<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Models\ZoomMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells admins that a user clicked "Keep my license". */
class LicenseKept extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Organization $organization, public ZoomMember $member) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[SeatTrim] {$this->member->email} kept their Zoom license")
            ->line("**{$this->member->name}** ({$this->member->email}) clicked *Keep my license* in the warning email for {$this->organization->name}.")
            ->line('The scheduled downgrade was cancelled and the user is excluded from automation for 90 days.')
            ->action('Open SeatTrim', route('members', ['search' => $this->member->email]));
    }
}
