<x-layouts::public :title="$post['title']" :description="$post['description']" :jsonLd="['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post['title'], 'description' => $post['description'], 'datePublished' => $post['published_at'], 'author' => ['@type' => 'Organization', 'name' => 'SeatTrim'], 'publisher' => ['@type' => 'Organization', 'name' => 'StaticMaker Pte Ltd']]">
    <article class="prose prose-zinc mx-auto max-w-3xl dark:prose-invert">
        <p class="text-xs uppercase tracking-wide text-zinc-500">{{ $post['type'] === 'hub' ? __('Guide') : __('Explainer') }} · {{ \Carbon\Carbon::parse($post['published_at'])->toFormattedDateString() }}</p>
        <h1>{{ $post['title'] }}</h1>
        <p class="lead">{{ $post['description'] }}</p>
        <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm not-prose dark:border-zinc-600">
            <div class="font-semibold">{{ __('Draft outline (content coming soon)') }}</div>
            <ol class="mt-2 list-decimal space-y-1 ps-5">
                @foreach ($post['outline'] as $item)<li>{{ $item }}</li>@endforeach
            </ol>
        </div>
        @if ($post['type'] === 'spoke')
            <p>{{ __('Part of the guide') }} <a href="{{ route('blog.show', 'how-to-free-up-zoom-licenses') }}">{{ __('How to free up Zoom licenses') }}</a>.</p>
        @else
            <p>{{ __('Read next:') }} <a href="{{ route('blog.show', 'zoom-inactive-users-report-explained') }}">{{ __('Zoom inactive users report, explained') }}</a> · <a href="{{ route('blog.show', 'zoom-deactivated-user-still-using-a-license') }}">{{ __('Deactivated user still using a license') }}</a></p>
        @endif
        <p><a href="{{ route('free-audit') }}">{{ __('Run a free Zoom license audit →') }}</a></p>
    </article>
</x-layouts::public>
