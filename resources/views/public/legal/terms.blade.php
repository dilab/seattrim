<x-layouts::public :title="__('Terms of service')" :description="__('Terms of service for SeatTrim, provided by StaticMaker Pte Ltd, Singapore.')">
    <article class="prose prose-zinc mx-auto max-w-3xl dark:prose-invert">
        <h1>{{ __('Terms of service') }}</h1>
        <p><em>{{ __('Last updated 25 September 2026. These terms are between you (the organization using SeatTrim) and StaticMaker Pte Ltd, Singapore ("StaticMaker", "we").') }}</em></p>
        <h2>{{ __('The service') }}</h2>
        <p>{{ __('SeatTrim analyses your Zoom account and, when you instruct it, changes users between Licensed and Basic through Zoom\'s API. You are responsible for the changes you request or automate, and for lowering seat quantities with Zoom. SeatTrim does not change your Zoom subscription or guarantee any saving.') }}</p>
        <h2>{{ __('Your obligations') }}</h2>
        <ul>
            <li>{{ __('You have authority to connect the Zoom account and to change your users\' license types.') }}</li>
            <li>{{ __('You will inform your users as required by law about the processing of their data and about warning emails sent on your behalf.') }}</li>
            <li>{{ __('You will keep your credentials secure and not misuse the service.') }}</li>
        </ul>
        <h2>{{ __('Plans and payment') }}</h2>
        <p>{{ __('The free plan is provided as is. Paid plans are billed yearly in advance via Stripe, renew automatically, and can be cancelled any time with access continuing to the end of the paid period. Prices may change with 30 days\' notice; changes apply at the next renewal.') }}</p>
        <h2>{{ __('Availability and support') }}</h2>
        <p>{{ __('We aim for high availability but do not guarantee uninterrupted service. Support terms are on the support page.') }}</p>
        <h2>{{ __('Liability') }}</h2>
        <p>{{ __('To the extent permitted by law, our liability is limited to the fees you paid in the 12 months before the claim. We are not liable for indirect losses, including losses caused by license changes you requested or automated.') }}</p>
        <h2>{{ __('Termination') }}</h2>
        <p>{{ __('You may stop using SeatTrim at any time; disconnecting deletes your Zoom data as described in the privacy policy. We may suspend accounts that breach these terms.') }}</p>
        <h2>{{ __('Governing law') }}</h2>
        <p>{{ __('Singapore law; courts of Singapore.') }}</p>
        <p>{{ __('SeatTrim is not affiliated with, endorsed by or sponsored by Zoom Video Communications, Inc.') }}</p>
    </article>
</x-layouts::public>
