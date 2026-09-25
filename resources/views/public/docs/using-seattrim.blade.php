<x-public.docs-layout :title="__('Using SeatTrim')" :description="__('How the dashboard, buckets, guardrails, downgrades, automation, exclusions and renewal reminders work.')">
    <h1>{{ __('Using SeatTrim') }}</h1>
    <h2>{{ __('Dashboard') }}</h2>
    <p>{{ __('The headline is the annual cost of licensed seats you are paying for but not using, at your seat price. Below it: purchased, assigned and unassigned seats; the renewal card; and one card per bucket.') }}</p>
    <h2>{{ __('Buckets') }}</h2>
    <ul>
        <li><strong>{{ __('Deactivated, still licensed') }}</strong> — {{ __('the user is deactivated in Zoom but still holds a Licensed seat.') }}</li>
        <li><strong>{{ __('Pending invite, licensed') }}</strong> — {{ __('the invitation was never accepted, yet a seat is reserved.') }}</li>
        <li><strong>{{ __('Idle licensed') }}</strong> — {{ __('active and licensed, but has not hosted a meeting within your threshold (30, 60, 90 or 180 days). Joining meetings does not count.') }}</li>
        <li><strong>{{ __('Protected') }}</strong> — {{ __('would be idle, but a guardrail applies.') }}</li>
        <li><strong>{{ __('Healthy') }}</strong> — {{ __('basic users, and licensed users who host.') }}</li>
    </ul>
    <h2>{{ __('Guardrails') }}</h2>
    <p>{{ __('A user is never suggested for downgrade, and automation never touches them, if they are an account owner or admin, a Zoom Room, on a Workplace or United bundle, have Zoom Phone, Webinar, Large Meeting or another add-on, have upcoming scheduled meetings, match an exclusion, clicked "Keep my license", or were created less than 30 days ago. SeatTrim re-checks all of this in Zoom immediately before any change.') }}</p>
    <h2>{{ __('Downgrade and restore') }}</h2>
    <p>{{ __('Open a member from the table and click Downgrade to Basic. SeatTrim re-reads the user, re-runs the guardrails, changes the type, reads the user again to confirm, and writes an audit entry with Zoom\'s tracking id. Restore puts the Licensed type back as long as Zoom has a free seat. Paid plans add bulk actions with a safety cap of max(10, 10% of licensed seats) per run.') }}</p>
    <p><strong>{{ __('Important:') }}</strong> {{ __('downgrading does not reduce your Zoom bill by itself. Lower the seat count in Zoom Billing, usually at renewal.') }}</p>
    <h2>{{ __('Automation (paid)') }}</h2>
    <p>{{ __('Turn on the rule, keep dry run on for a couple of weeks, and pick the buckets. Users get a plain email a week before with a "Keep my license" button that excludes them for 90 days and tells you. On the day, still-eligible users are downgraded after a fresh check. Everything is in the audit log with source "rule".') }}</p>
    <h2>{{ __('Exclusions') }}</h2>
    <p>{{ __('Add emails, email domains or Zoom group ids to keep whole teams out of the report, with an optional expiry date.') }}</p>
    <h2>{{ __('Renewal') }}</h2>
    <p>{{ __('Enter your renewal date and SeatTrim shows the seat count to reduce to, and emails owners and admins 60, 30 and 7 days before.') }}</p>
</x-public.docs-layout>
