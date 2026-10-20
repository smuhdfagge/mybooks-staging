{{--
    One guide (SEO plan). The article carries its own title, description,
    dates and Article structured data, which is most of what makes it
    findable at all.

    The body is markdown from resources/guides, written and reviewed in
    this repository, so rendering it as HTML is safe here. If guides ever
    come from anywhere else, this must be sanitised.
--}}
@php
    $published = $guide['published'] ? \Illuminate\Support\Carbon::parse($guide['published']) : null;
    $updated = $guide['updated'] ? \Illuminate\Support\Carbon::parse($guide['updated']) : $published;
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $guide['heading'],
        'description' => $guide['description'] ?: null,
        'datePublished' => $published?->toDateString(),
        'dateModified' => $updated?->toDateString(),
        'inLanguage' => 'en-NG',
        'author' => ['@type' => 'Organization', 'name' => 'MyBooks'],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'MyBooks',
            'logo' => ['@type' => 'ImageObject', 'url' => asset('images/brand/mybooks-logo-email-300.png')],
        ],
        'mainEntityOfPage' => route('guides.show', $guide['slug']),
    ], fn ($value) => $value !== null);
@endphp

<x-public.layout :title="$guide['title']" :description="$guide['description']" :schema="$schema">
    <article class="mx-auto max-w-3xl px-4 pb-16 pt-14 sm:px-6 sm:pt-20">
        <nav class="text-sm text-gray-600 dark:text-gray-400" aria-label="Breadcrumb">
            <a href="{{ route('guides.index') }}" class="underline hover:text-gray-900 dark:hover:text-white">Guides</a>
        </nav>

        <h1 class="mt-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">{{ $guide['heading'] }}</h1>

        @if ($guide['description'])
            <p class="mt-4 text-lg text-gray-700 dark:text-gray-300">{{ $guide['description'] }}</p>
        @endif

        @if ($updated)
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Last checked <time datetime="{{ $updated->toDateString() }}">{{ $updated->format('j F Y') }}</time>.
                This is general information, not tax advice for your own business.
            </p>
        @endif

        <div class="mb-prose mt-10">
            {!! \Illuminate\Support\Str::markdown($guide['body'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
        </div>
    </article>

    @if ($more->isNotEmpty())
        <section class="border-t border-gray-200 dark:border-gray-800" aria-label="More guides">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">More guides</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($more as $other)
                        <li><a href="{{ route('guides.show', $other['slug']) }}" class="font-medium text-brand-600 underline dark:text-brand-300">{{ $other['heading'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
        <div class="mx-auto max-w-3xl px-4 py-12 text-center sm:px-6">
            <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">MyBooks does this for you</h2>
            <p class="mt-3 text-gray-700 dark:text-gray-300">
                Invoices, VAT and withholding tax, built for Nigerian businesses. See what it costs, or book a demo.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 font-semibold text-white hover:bg-brand-700 focus-ring">See pricing</a>
                <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-900 hover:bg-white focus-ring dark:border-gray-700 dark:text-white dark:hover:bg-gray-800">Book a demo</a>
            </div>
        </div>
    </section>
</x-public.layout>
