<x-layouts::auth :title="__('Keep my Zoom license')">
    <div class="space-y-6 text-center">
        <flux:heading size="xl">{{ __('Your Zoom license at :org', ['org' => $organization->name]) }}</flux:heading>

        @if (session('kept') || $alreadyKept)
            <flux:callout icon="check-circle" variant="success">
                <flux:callout.text>{{ __('Done. Your Licensed account stays as it is for the next 90 days, and your admins have been told. You can close this page.') }}</flux:callout.text>
            </flux:callout>
        @elseif ($closed)
            <flux:callout icon="information-circle" variant="secondary">
                <flux:callout.text>{{ __('This notice is no longer open: the change was already made or cancelled. If you need a Licensed account, contact your Zoom admin.') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:text>{{ __(':email is scheduled to switch from Licensed to Basic on :date because it has not hosted a meeting recently. Click below to keep the license for 90 days.', ['email' => $member->email, 'date' => $notice->scheduled_for->timezone($organization->timezone)->toFormattedDateString()]) }}</flux:text>
            <form method="POST" action="{{ route('keep-license.store', ['notice' => $notice->id, 'signature' => request('signature'), 'expires' => request('expires')]) }}">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full" data-test="keep-license-button">{{ __('Keep my license') }}</flux:button>
            </form>
            <flux:text class="text-xs">{{ __('No sign-in needed. This link only works for your account and expires after the scheduled date.') }}</flux:text>
        @endif
    </div>
</x-layouts::auth>
