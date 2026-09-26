<x-layouts::public :title="__('Blog')" :description="__('Guides for Zoom admins on finding and reclaiming unused licenses.')">
    <x-slot name="hero">
        <x-public.page-hero :label="__('Blog')" :heading="__('Notes for Zoom admins.')" :muted="__('Short, practical, no hype.')" />
    </x-slot>

    <section class="grid gap-6 md:grid-cols-3">
        @foreach ($posts as $slug => $post)
            <article class="flex flex-col gap-3 rounded-3xl bg-white p-6 shadow-card">
                <div class="flex items-center gap-2 text-[11px] text-ink-400"><x-public.pill :tone="$post['type'] === 'hub' ? 'brand' : 'ink'">{{ $post['type'] === 'hub' ? __('Guide') : __('Explainer') }}</x-public.pill>{{ \Carbon\Carbon::parse($post['published_at'])->toFormattedDateString() }}</div>
                <h2 class="text-[18px] font-medium leading-[1.3]"><a href="{{ route('blog.show', $slug) }}" class="text-ink-900 hover:text-brand-600">{{ $post['title'] }}</a></h2>
                <p class="text-[13px] leading-relaxed text-ink-500">{{ $post['description'] }}</p>
            </article>
        @endforeach
    </section>
</x-layouts::public>
