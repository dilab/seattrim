<x-layouts::public :title="__('Privacy policy')" :description="__('How SeatTrim (StaticMaker Pte Ltd) collects, uses, stores and deletes data, including data from your Zoom account.')">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Legal')" :heading="__('Privacy policy')" :intro="__('Last updated 25 September 2026. Controller: StaticMaker Pte Ltd, Singapore (“we”).')" />
    </x-slot>

    <x-public.article class="mx-auto w-full max-w-3xl">
        <h2>{{ __('What we collect') }}</h2>
        <ul>
            <li><strong>{{ __('Account data:') }}</strong> {{ __('your name, email address and password hash; organization name, timezone, seat price and renewal date you enter; billing records via Stripe (we never see card numbers).') }}</li>
            <li><strong>{{ __('Zoom data, when you connect:') }}</strong> {{ __('for each user on your Zoom account: Zoom user id, email, display name, department, group ids, role, license type, status, creation date, last login time, hosted-meeting counts per 30-day window, add-on and bundle flags, and the count of upcoming scheduled meetings. Encrypted OAuth tokens. Plan usage totals. We do not collect meeting content, recordings, chat messages or participant lists.') }}</li>
            <li><strong>{{ __('Technical data:') }}</strong> {{ __('server logs with IP address and user agent, kept 30 days. Emails in logs are redacted where practical.') }}</li>
        </ul>
        <h2>{{ __('Why') }}</h2>
        <p>{{ __('To show you which Zoom licenses are unused, to perform the license changes you request, to send the emails you enable (warnings to your users, digests and renewal reminders to your admins), to bill you, and to keep the service secure. We do not sell or rent personal data, use it for advertising, or build profiles.') }}</p>
        <h2>{{ __('Legal basis') }}</h2>
        <p>{{ __('Performance of our contract with your organization, and our legitimate interest in running and securing the service. Warning emails to your Zoom users are sent on your organization’s instruction.') }}</p>
        <h2>{{ __('Retention and deletion') }}</h2>
        <p>{{ __('Zoom-derived data is deleted immediately when you disconnect in SeatTrim or remove the app in the Zoom App Marketplace. We keep an anonymised count of audit actions and billing records required by law. Account data is deleted within 30 days of deleting your account.') }}</p>
        <h2>{{ __('Processors') }}</h2>
        <p>{{ __('Zoom Video Communications (API), Stripe (payments), our hosting provider, Cloudflare (network), and our email delivery provider. Each is bound by terms that protect your data at least as strictly as this policy.') }}</p>
        <h2>{{ __('Your rights') }}</h2>
        <p>{{ __('You can access, correct, export or delete your data from the app or by emailing privacy@seattrim.com. Zoom users who receive a warning email can ask their organization’s admin, who can exclude them or delete the data.') }}</p>
        <h2>{{ __('Contact') }}</h2>
        <p>{{ __('StaticMaker Pte Ltd, Singapore · privacy@seattrim.com') }}</p>
    </x-public.article>
</x-layouts::public>
