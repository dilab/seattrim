<x-layouts::public :title="__('Support')" :description="__('How to reach SeatTrim support, our hours and response time.')">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Support')" :heading="__('We answer like a careful colleague.')" :intro="__('SeatTrim is built and supported by StaticMaker Pte Ltd in Singapore.')" />
    </x-slot>

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
    <p class="text-[14px] text-ink-500">{{ __('Security issue? Email security@seattrim.com. Please do not include Zoom tokens or personal data in reports.') }}</p>
</x-layouts::public>
