<?php

namespace App\Notifications;

use App\Automation\AutomationSettings;
use App\Models\DowngradeNotice;
use App\Models\Organization;
use App\Models\ZoomMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** Plain, non-scary notice to the Zoom user, with a signed "Keep my license" link. */
class DowngradeWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public ZoomMember $member,
        public DowngradeNotice $notice,
        public AutomationSettings $settings,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->settings->thresholdDays($this->organization);
        $date = $this->notice->scheduled_for->timezone($this->organization->timezone)->toFormattedDateString();

        $mail = (new MailMessage)
            ->subject("Your Zoom license at {$this->organization->name}")
            ->greeting('Hi '.($this->member->name ?: 'there').',')
            ->line("{$this->organization->name} keeps its Zoom licenses tidy with SeatTrim. Your account has not hosted a Zoom meeting in the last {$days} days, so on **{$date}** it will be switched from a Licensed account to a Basic one.")
            ->line('Basic accounts can still join any meeting and host meetings of up to 40 minutes. Nothing else about your account changes, and an admin can switch you back at any time.')
            ->line('If you still need to host longer meetings, click below and your license stays as it is for the next '.AutomationSettings::KEEP_DAYS.' days. No sign-in needed.')
            ->action('Keep my license', $this->keepUrl())
            ->line('If you are happy to be switched to Basic, you do not need to do anything.');

        if ($this->settings->replyTo) {
            $mail->replyTo($this->settings->replyTo);
            $mail->line("Questions? Reply to this email and it reaches {$this->settings->replyTo}.");
        }

        return $mail;
    }

    public function keepUrl(): string
    {
        return URL::temporarySignedRoute('keep-license', $this->notice->scheduled_for->addDays(2), ['notice' => $this->notice->id]);
    }
}
