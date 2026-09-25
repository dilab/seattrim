<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Weekly admin digest (brief §8). Numbers are prepared by the DigestBuilder. */
class WeeklyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(public Organization $organization, public array $data) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->data;
        $mail = (new MailMessage)
            ->subject("[SeatTrim] Weekly Zoom license digest for {$this->organization->name}")
            ->greeting('Hello,')
            ->line("Here is what happened with Zoom licenses at **{$this->organization->name}** in the last 7 days.");

        $mail->line("**Downgraded:** {$d['downgraded']} · **Kept by user:** {$d['kept']} · **Skipped or failed:** {$d['skipped']} · **Pending warnings:** {$d['pending']}");

        if ($d['dry_run_would']) {
            $mail->line("Automation is in dry-run mode: {$d['dry_run_would']} user(s) would have been downgraded. Turn dry run off in Automation settings when you are ready.");
        }

        $mail->line('**Current waste by bucket**');
        foreach ($d['buckets'] as $bucket) {
            $mail->line("- {$bucket['label']}: {$bucket['count']} ({$bucket['money']}/yr)");
        }
        if ($d['unassigned'] !== null) {
            $mail->line("- Unassigned seats: {$d['unassigned']['count']} ({$d['unassigned']['money']}/yr)");
        }

        if ($d['renewal']) {
            $mail->line("**Renewal:** {$d['renewal']}");
        }

        return $mail
            ->action('Open dashboard', route('dashboard'))
            ->line('Reminder: downgrades free seats but your Zoom bill only changes when you lower the seat count in Zoom Billing.');
    }
}
