{{--
    The guides listing (SEO plan). Short: its job is to point at the
    articles, which are the pages that get found.
--}}
<x-public.layout
    title="Guides for Nigerian businesses"
    description="Plain-English guides to VAT, withholding tax and keeping books in Nigeria, written for business owners rather than accountants.">

    <section class="mx-auto max-w-3xl px-4 pb-6 pt-14 sm:px-6 sm:pt-20">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">Guides</h1>
        <p class="mt-4 text-lg text-gray-700 dark:text-gray-300">
            Plain English about VAT, withholding tax and keeping books in Nigeria. Written for business owners, not for accountants.
        </p>
    </section>

    <section class="mx-auto max-w-3xl px-4 pb-20 sm:px-6">
        @forelse ($guides as $guide)
            <article class="border-t border-gray-200 py-8 first:border-t-0 first:pt-0 dark:border-gray-800">
                <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
                    <a href="{{ route('guides.show', $guide['slug']) }}" class="hover:underline">{{ $guide['heading'] }}</a>
                </h2>
                @if ($guide['description'])
                    <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $guide['description'] }}</p>
                @endif
                @if ($guide['updated'])
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Last checked <time datetime="{{ $guide['updated'] }}">{{ \Illuminate\Support\Carbon::parse($guide['updated'])->format('j F Y') }}</time>
                    </p>
                @endif
            </article>
        @empty
            <p class="text-gray-700 dark:text-gray-300">The first guides are being written. In the meantime, <a href="{{ route('contact') }}" class="font-medium text-brand-600 underline dark:text-brand-300">ask us</a> and we will answer.</p>
        @endforelse
    </section>
</x-public.layout>
