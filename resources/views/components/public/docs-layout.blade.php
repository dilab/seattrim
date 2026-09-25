@props(['title', 'description'])
<x-layouts::public :title="$title" :description="$description">
    <div class="grid gap-10 md:grid-cols-[220px_1fr]">
        <nav class="text-sm">
            <div class="font-semibold">{{ __('Documentation') }}</div>
            <ul class="mt-3 space-y-2">
                @foreach (['index' => __('Overview'), 'add-the-app' => __('Adding the app'), 'using-seattrim' => __('Using SeatTrim'), 'remove-the-app' => __('Removing the app and your data'), 'troubleshooting' => __('Troubleshooting'), 'faq' => __('FAQ')] as $slug => $label)
                    <li><a href="{{ $slug === 'index' ? route('docs.index') : route('docs.show', $slug) }}" class="hover:underline {{ (request()->routeIs('docs.index') && $slug === 'index') || request()->route('page') === $slug ? 'font-semibold text-blue-600' : '' }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>
        <article class="prose prose-zinc max-w-none dark:prose-invert">
            {{ $slot }}
        </article>
    </div>
</x-layouts::public>
