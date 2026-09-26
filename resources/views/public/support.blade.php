<x-layouts::public :title="__('Support')" :description="__('How to reach SeatTrim support, our hours and response time.')">
    <section class="flex flex-col gap-5 pt-6">
        <x-public.section-label>{{ __('Support') }}</x-public.section-label>
        <h1 class="text-[36px] font-light leading-[1.1] tracking-[-0.02em] md:text-[48px]">{{ __('We answer like a careful colleague.') }}</h1>
        <p class="max-w-xl text-[15px] leading-relaxed text-ink-500">{{ __('SeatTrim is built and supported by StaticMaker Pte Ltd in Singapore.') }}</p>
    </section>
    <section class="grid gap-6 md:grid-cols-2">
        @foreach ([
            [__('Email'), '<a href="mailto:support@seattrim.com">support@seattrim.com</a>'],
            [__('Hours'), e(__('Monday to Friday, 09:00–18:00 Singapore time (UTC+8)'))],
            [__('First response'), e(__('Within one business day. Paid plans: within 4 business hours.'))],
            [__('Self-service'), '<a href="'.route('docs.index').'">'.e(__('Documentation')).'</a> · <a href="'.route('docs.show', 'troubleshooting').'">'.e(__('Troubleshooting')).'</a>'],
        ] as [$dt, $dd])
            <div class="flex flex-col gap-1 rounded-3xl bg-white p-6 shadow-card"><div class="text-[13px] font-medium text-ink-900">{{ $dt }}</div><div class="text-[15px] text-ink-700">{!! $dd !!}</div></div>
        @endforeach
    </section>
    <p class="text-[13px] text-ink-500">{{ __('Security issue? Email security@seattrim.com. Please do not include Zoom tokens or personal data in reports.') }}</p>
</x-layouts::public>
