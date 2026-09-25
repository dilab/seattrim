<x-public.docs-layout :title="__('Adding SeatTrim to your Zoom account')" :description="__('Step-by-step: requirements, authorizing the SeatTrim Zoom app as an admin, scopes, and the first scan.')">
    <h1>{{ __('Adding the app') }}</h1>
    <h2>{{ __('Requirements') }}</h2>
    <ul>
        <li>{{ __('A Zoom account on Pro, Business, Education or Enterprise. Free Zoom accounts do not expose the host report; SeatTrim will connect but cannot detect idle hosts.') }}</li>
        <li>{{ __('You are the Zoom account owner or an admin whose role can view usage reports and edit users.') }}</li>
        <li>{{ __('If your Zoom admin requires Marketplace apps to be pre-approved, ask them to pre-approve SeatTrim first (Zoom App Marketplace → Manage → App Requests).') }}</li>
    </ul>
    <h2>{{ __('Steps') }}</h2>
    <ol>
        <li>{{ __('Create a SeatTrim account and an organization (one organization per Zoom account).') }}</li>
        <li>{{ __('Click Connect Zoom. You are sent to zoom.us to sign in and review the requested permissions.') }}</li>
        <li>{{ __('Click Allow. Zoom sends you back to SeatTrim.') }}</li>
        <li>{{ __('Optionally enter your per-seat price, billing cycle and renewal date. You can skip this and add it later in Settings.') }}</li>
        <li>{{ __('The first scan runs. It reads users, the last 90 days of host reports (180 if you choose that threshold) and plan usage. Most accounts finish in under a minute.') }}</li>
    </ol>
    <h2>{{ __('Adding from the Zoom App Marketplace') }}</h2>
    <p>{{ __('You can also start from the SeatTrim listing on the Zoom App Marketplace: click Visit Site to Add, sign in to SeatTrim or create an account, and follow the same steps.') }}</p>
    <h2>{{ __('Troubleshooting') }}</h2>
    <p>{{ __('"Invalid redirect", "You do not have permission", or a scan that reports missing scopes: see') }} <a href="{{ route('docs.show', 'troubleshooting') }}">{{ __('Troubleshooting') }}</a>.</p>
</x-public.docs-layout>
