<x-public.docs-layout :title="__('Removing SeatTrim and what happens to your data')" :heading="__('Removing the app')" :intro="__('Two ways to disconnect, and exactly what is deleted when you do.')" :description="__('Two ways to remove SeatTrim from your Zoom account, and exactly which data is deleted when you do.')">
    <h2>{{ __('Option 1: from SeatTrim') }}</h2>
    <ol>
        <li>{{ __('Sign in as an organization owner and open Connection.') }}</li>
        <li>{{ __('Click Disconnect and delete data, type DELETE, confirm.') }}</li>
    </ol>
    <h2>{{ __('Option 2: from the Zoom App Marketplace') }}</h2>
    <ol>
        <li>{{ __('Sign in to the Zoom App Marketplace and click Manage.') }}</li>
        <li>{{ __('Open Added Apps, find SeatTrim and click Remove.') }}</li>
        <li>{{ __('Choose a reason (optional) and confirm.') }}</li>
    </ol>
    <p>{{ __('Zoom sends SeatTrim a deauthorization notification and SeatTrim performs the same clean-up as Option 1.') }}</p>
    <h2>{{ __('What happens to your data') }}</h2>
    <ul>
        <li>{{ __('SeatTrim revokes its Zoom access token and deletes the stored (encrypted) tokens.') }}</li>
        <li>{{ __('It deletes, immediately: the user inventory, seat snapshots, scans, per-user audit entries and warning notices for that organization.') }}</li>
        <li>{{ __('It keeps: your SeatTrim account, organization name and members, exclusion rules you typed in, billing records required by law, and an anonymised count of audit actions.') }}</li>
        <li>{{ __('Nothing changes in Zoom itself. Users keep whatever license type they have at that moment. Any scheduled automation is cancelled.') }}</li>
    </ul>
    <p>{{ __('Owners receive an email confirming the removal. You can reconnect at any time; the first scan afterwards rebuilds the report from scratch. To delete your SeatTrim account entirely, use Settings → Profile → Delete account, or email support@seattrim.com.') }}</p>
</x-public.docs-layout>
