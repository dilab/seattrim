<x-public.docs-layout :title="__('FAQ')" :description="__('Frequently asked questions about SeatTrim: authorization, emails, unsubscribing, data handling.')">
    <h1>{{ __('FAQ') }}</h1>
    <h2>{{ __('Does SeatTrim lower my Zoom bill?') }}</h2><p>{{ __('Not directly. It frees seats and tells you how many to cut. You change the quantity in Zoom Billing, typically at renewal. Annual plans do not refund mid-term.') }}</p>
    <h2>{{ __('Will it downgrade someone by surprise?') }}</h2><p>{{ __('No. Manual actions need a click; automation is off by default, starts in dry run, warns the user by email first, and re-checks guardrails before acting.') }}</p>
    <h2>{{ __('Can users opt out of the warning emails?') }}</h2><p>{{ __('The email is sent only when a downgrade is scheduled for them, at most once per notice. Clicking "Keep my license" excludes them for 90 days; admins can add them to exclusions permanently.') }}</p>
    <h2>{{ __('What data do you store?') }}</h2><p>{{ __('User id, email, name, department, license type, status, role, creation date, last login, hosting counts per window, add-on flags, and the number of upcoming meetings. No meeting content. See the') }} <a href="{{ route('privacy') }}">{{ __('privacy policy') }}</a>.</p>
    <h2>{{ __('Where is data hosted?') }}</h2><p>{{ __('On servers operated by StaticMaker Pte Ltd behind Cloudflare. Zoom tokens are encrypted at rest.') }}</p>
    <h2>{{ __('I did not get the activation email') }}</h2><p>{{ __('Check spam for a message from SeatTrim, or request a new link from the sign-in page.') }}</p>
</x-public.docs-layout>
