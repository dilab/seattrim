<x-layouts::public :title="__('Support')" :description="__('How to reach SeatTrim support, our hours and response time.')">
    <div class="mx-auto max-w-3xl space-y-6">
        <h1 class="text-4xl font-semibold tracking-tight">{{ __('Support') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-300">{{ __('SeatTrim is built and supported by StaticMaker Pte Ltd in Singapore.') }}</p>
        <dl class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><dt class="font-semibold">{{ __('Email') }}</dt><dd class="mt-1"><a href="mailto:support@seattrim.com" class="text-blue-600 underline">support@seattrim.com</a></dd></div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><dt class="font-semibold">{{ __('Hours') }}</dt><dd class="mt-1">{{ __('Monday to Friday, 09:00–18:00 Singapore time (UTC+8)') }}</dd></div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><dt class="font-semibold">{{ __('First response') }}</dt><dd class="mt-1">{{ __('Within one business day. Paid plans: within 4 business hours.') }}</dd></div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><dt class="font-semibold">{{ __('Self-service') }}</dt><dd class="mt-1"><a href="{{ route('docs.index') }}" class="text-blue-600 underline">{{ __('Documentation') }}</a> · <a href="{{ route('docs.show', 'troubleshooting') }}" class="text-blue-600 underline">{{ __('Troubleshooting') }}</a></dd></div>
        </dl>
        <p class="text-sm text-zinc-500">{{ __('Security issue? Email security@seattrim.com. Please do not include Zoom tokens or personal data in reports.') }}</p>
    </div>
</x-layouts::public>
