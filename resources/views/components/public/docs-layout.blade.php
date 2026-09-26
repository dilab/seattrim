@props(['title', 'description', 'heading' => null, 'intro' => null])
<x-layouts::public :title="$title" :description="$description">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Documentation')" :heading="$heading ?? $title" :intro="$intro" />
    </x-slot>

    <div class="grid gap-8 md:grid-cols-[220px_1fr]">
        <nav class="md:sticky md:top-6 md:self-start" aria-label="Documentation">
            <x-public.section-label>{{ __('Documentation') }}</x-public.section-label>
            <ul class="mt-4 flex flex-col gap-1 border-l border-ink-200 text-[14px]">
                @foreach (['index' => __('Overview'), 'add-the-app' => __('Adding the app'), 'using-seattrim' => __('Using SeatTrim'), 'remove-the-app' => __('Removing the app and your data'), 'troubleshooting' => __('Troubleshooting'), 'faq' => __('FAQ')] as $slug => $label)
                    @php($active = (request()->routeIs('docs.index') && $slug === 'index') || request()->route('page') === $slug)
                    <li><a href="{{ $slug === 'index' ? route('docs.index') : route('docs.show', $slug) }}" class="block py-1 pl-4 {{ $active ? '-ml-px border-l-2 border-coral-500 font-medium text-ink-900' : 'text-ink-500 hover:text-ink-900' }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <x-public.article class="max-w-none">{{ $slot }}</x-public.article>
    </div>
</x-layouts::public>
