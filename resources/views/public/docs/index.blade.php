<x-public.docs-layout :title="__('Documentation')" :heading="__('SeatTrim documentation')" :intro="__('SeatTrim is an admin-managed Zoom Marketplace app that finds Licensed seats nobody uses and helps you downgrade them safely. These pages cover adding the app, day-to-day use, and removal.')" :description="__('How to add SeatTrim to your Zoom account, use it, and remove it, including what happens to your data.')">
    <ul>
        <li><a href="{{ route('docs.show', 'add-the-app') }}">{{ __('Adding the app') }}</a> — {{ __('requirements, the Zoom authorization screen, scopes and the first scan.') }}</li>
        <li><a href="{{ route('docs.show', 'using-seattrim') }}">{{ __('Using SeatTrim') }}</a> — {{ __('dashboard, buckets, guardrails, downgrades, automation, exclusions, renewal.') }}</li>
        <li><a href="{{ route('docs.show', 'remove-the-app') }}">{{ __('Removing the app') }}</a> — {{ __('two ways to disconnect and exactly what data is deleted.') }}</li>
        <li><a href="{{ route('docs.show', 'troubleshooting') }}">{{ __('Troubleshooting') }}</a> · <a href="{{ route('docs.show', 'faq') }}">{{ __('FAQ') }}</a></li>
    </ul>
    <h2>{{ __('Scopes SeatTrim requests') }}</h2>
    <table>
        <thead><tr><th>{{ __('Scope') }}</th><th>{{ __('Used for') }}</th></tr></thead>
        <tbody>
            <tr><td><code>user:read:list_users:admin</code></td><td>{{ __('List users in every status to see who holds a Licensed seat.') }}</td></tr>
            <tr><td><code>user:read:user:admin</code></td><td>{{ __('Re-read one user before any change (type, status, role, bundle).') }}</td></tr>
            <tr><td><code>user:read:settings:admin</code></td><td>{{ __('Read add-on flags (Zoom Phone, Webinar, Large Meeting) so those users are protected.') }}</td></tr>
            <tr><td><code>user:update:user:admin</code></td><td>{{ __('Change a user between Licensed and Basic when you ask. The only write.') }}</td></tr>
            <tr><td><code>report:read:list_users:admin</code></td><td>{{ __('Active/inactive hosts report: who hosted meetings in each 30-day window.') }}</td></tr>
            <tr><td><code>billing:read:plan_usage:admin</code></td><td>{{ __('Purchased versus assigned seats.') }}</td></tr>
            <tr><td><code>meeting:read:list_meetings:admin</code></td><td>{{ __('Upcoming meetings of downgrade candidates (topic and time only) so hosts with scheduled meetings are protected.') }}</td></tr>
        </tbody>
    </table>
    <p>{{ __('SeatTrim never reads meeting content, recordings, chat or participant lists.') }}</p>
</x-public.docs-layout>
