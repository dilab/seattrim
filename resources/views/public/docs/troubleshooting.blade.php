<x-public.docs-layout :title="__('Troubleshooting')" :description="__('Fixes for common SeatTrim issues: authorization errors, missing scopes, empty reports, failed downgrades.')">
    <h1>{{ __('Troubleshooting') }}</h1>
    <h2>{{ __('Zoom says I do not have permission to add the app') }}</h2>
    <p>{{ __('Only an account owner or admin can add an admin-managed app. Your Zoom admin may also need to pre-approve SeatTrim in the Marketplace.') }}</p>
    <h2>{{ __('The scan shows "missing scopes"') }}</h2>
    <p>{{ __('The Zoom role that authorized the app lacks a permission (for example usage reports or billing). Ask an owner to reconnect from the Connection page.') }}</p>
    <h2>{{ __('Nobody is idle, every licensed user is healthy') }}</h2>
    <p>{{ __('Check the data-quality warnings on the dashboard. If the host report failed or the account is on a free Zoom plan, hosting is unknown and SeatTrim never marks anyone idle on unknown data.') }}</p>
    <h2>{{ __('Purchased seats show "?"') }}</h2>
    <p>{{ __('Zoom did not expose plan usage for this account. Assigned seats still come from the user summary. Check the purchased quantity in Zoom Billing.') }}</p>
    <h2>{{ __('A downgrade failed with Zoom error 200 "A Zoom Room user cannot be changed"') }}</h2>
    <p>{{ __('That user is a Zoom Room. SeatTrim marks it as a room and protects it from now on.') }}</p>
    <h2>{{ __('A restore failed with "No free Licensed seat"') }}</h2>
    <p>{{ __('All purchased seats are assigned. Free one, or buy one in Zoom Billing, then restore again.') }}</p>
    <h2>{{ __('The onboarding scan never finishes') }}</h2>
    <p>{{ __('Wait a couple of minutes for very large accounts. If it is stuck at "waiting for a worker", contact support; on self-hosted or local installs make sure a queue worker is running.') }}</p>
    <p>{{ __('Still stuck?') }} <a href="{{ route('support') }}">{{ __('Contact support') }}</a>.</p>
</x-public.docs-layout>
