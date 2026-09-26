<x-layouts::public :title="$post['title']" :description="$post['description']" :jsonLd="['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post['title'], 'description' => $post['description'], 'datePublished' => $post['published_at'], 'author' => ['@type' => 'Organization', 'name' => 'SeatTrim'], 'publisher' => ['@type' => 'Organization', 'name' => 'StaticMaker Pte Ltd']]">
    <article class="prose mx-auto mt-6 w-full max-w-3xl rounded-3xl bg-white p-6 shadow-card sm:p-8">
        <div class="not-prose flex items-center gap-2 text-[11px] text-ink-400"><x-public.pill :tone="$post['type'] === 'hub' ? 'brand' : 'ink'">{{ $post['type'] === 'hub' ? __('Guide') : __('Explainer') }}</x-public.pill>{{ \Carbon\Carbon::parse($post['published_at'])->toFormattedDateString() }}</div>
        <h1>{{ $post['title'] }}</h1>
        <p class="lead">{{ $post['description'] }}</p>
        <div class="not-prose rounded-2xl border border-dashed border-ink-200 bg-ink-100 p-4 text-[13px]">
            <div class="font-medium text-ink-900">{{ __('Draft outline (content coming soon)') }}</div>
            <ol class="mt-2 list-decimal space-y-1 ps-5 text-ink-700">
                @foreach ($post['outline'] as $item)<li>{{ $item }}</li>@endforeach
            </ol>
        </div>
        @if ($post['type'] === 'spoke')
            <p>{{ __('Part of the guide') }} <a href="{{ route('blog.show', 'how-to-free-up-zoom-licenses') }}">{{ __('How to free up Zoom licenses') }}</a>.</p>
        @else
            <p>{{ __('Read next:') }} <a href="{{ route('blog.show', 'zoom-inactive-users-report-explained') }}">{{ __('Zoom inactive users report, explained') }}</a> · <a href="{{ route('blog.show', 'zoom-deactivated-user-still-using-a-license') }}">{{ __('Deactivated user still using a license') }}</a></p>
        @endif
        <p><a href="{{ route('free-audit') }}">↳ {{ __('Run a free Zoom license audit') }}</a></p>
    </article>
</x-layouts::public>
