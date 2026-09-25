<x-layouts::public :title="__('Blog')" :description="__('Guides for Zoom admins on finding and reclaiming unused licenses.')">
    <h1 class="text-4xl font-semibold tracking-tight">{{ __('Blog') }}</h1>
    <div class="mt-8 space-y-6">
        @foreach ($posts as $slug => $post)
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="text-xs uppercase tracking-wide text-zinc-500">{{ $post['type'] === 'hub' ? __('Guide') : __('Explainer') }} · {{ \Carbon\Carbon::parse($post['published_at'])->toFormattedDateString() }}</div>
                <h2 class="mt-1 text-xl font-semibold"><a href="{{ route('blog.show', $slug) }}" class="hover:underline">{{ $post['title'] }}</a></h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $post['description'] }}</p>
            </article>
        @endforeach
    </div>
</x-layouts::public>
