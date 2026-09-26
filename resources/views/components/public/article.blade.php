{{-- Prose card with an "On this page" list built from its h2 headings (App\Support\Toc). --}}
@php($toc = App\Support\Toc::inject((string) $slot))
<article {{ $attributes->merge(['class' => 'prose rounded-3xl bg-white p-6 shadow-card sm:p-8 [&>*:first-child]:mt-0']) }}>
    @if (count($toc['items']) >= 3)
        <nav class="not-prose mb-8 rounded-2xl bg-ink-100 p-4" aria-label="{{ __('On this page') }}">
            <div class="text-[13px] font-medium text-ink-900">{{ __('On this page') }}</div>
            <ol class="mt-2 grid gap-x-6 gap-y-1 text-[14px] sm:grid-cols-2">
                @foreach ($toc['items'] as $item)
                    <li><a href="#{{ $item['id'] }}" class="text-brand-600 hover:text-brand-700 hover:underline">{{ $item['text'] }}</a></li>
                @endforeach
            </ol>
        </nav>
    @endif
    {!! $toc['html'] !!}
</article>
