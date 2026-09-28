<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** 60 / 30 / 7 days before the Zoom renewal, with the same numbers as the dashboard card (brief §9). */
class RenewalReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $figures */
    public function __construct(public Organization $organization, public int $daysBefore, public array $figures) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $f = $this->figures;
        $date = $this->organization->renewal_date?->toFormattedDateString() ?? '';

        $mail = (new MailMessage)
            ->subject("[SeatTrim] Zoom renews in {$this->daysBefore} days: right-size {$this->organization->name} before {$date}")
            ->greeting('Hello,')
            ->line("Your Zoom plan for **{$this->organization->name}** renews on **{$date}**, in {$this->daysBefore} days. This is the moment the seat count actually changes your bill.");

        if ($f['purchased'] !== null) {
            $mail->line("You pay for **{$f['purchased']}** seats, **{$f['used']}** are assigned, **{$f['reclaimable']}** are reclaimable (unassigned seats, pending invites, idle licensed users and any deactivated user still licensed).")
                ->line("Reduce to **{$f['target']}** seats → **{$f['target_money']}** per year (from {$f['current_money']}).");
        } else {
            $mail->line("**{$f['reclaimable']}** seats are reclaimable (pending invites, idle licensed users and any deactivated user still licensed). Zoom did not expose your purchased quantity to SeatTrim, so check it in Zoom Billing and subtract {$f['reclaimable']}.");
        }

        return $mail
            ->line('Before you lower the quantity: downgrade or delete the users you do not need, so Zoom lets you reduce. SeatTrim can do the downgrades; the quantity change happens in Zoom Billing.')
            ->action('Open Zoom Billing', 'https://zoom.us/billing')
            ->line('Dashboard: '.route('dashboard'));
    }
}
