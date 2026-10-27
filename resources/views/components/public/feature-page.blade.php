{{--
    The frame every feature page shares (SEO plan): the heading and the
    opening line, the slots a page fills, and the closing offer. Doing it
    once here keeps the pages consistent and makes the next one cheap.

    <x-public.feature-page :page="$page" heading="..." lead="...">
        ...the page's own sections...
        <x-slot:related>...links to the guides...</x-slot:related>
    </x-public.feature-page>
--}}
@props([
    'page',
    'heading',
    'lead',
    // Structured data for this page, merged into the default.
    'schema' => null,
])

@php
    $default = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $page['title'],
        'description' => $page['description'],
        'url' => \App\Support\PublicSite\CanonicalUrl::route('features.show', $page['slug']),
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'MyBooks', 'url' => \App\Support\PublicSite\CanonicalUrl::route('home')],
    ];
@endphp

<x-public.layout :title="$page['title']" :description="$page['description']" :schema="$schema ?? $default">
    <section class="mx-auto max-w-4xl px-4 pb-8 pt-14 sm:px-6 sm:pt-20">
        <h1 class="max-w-3xl text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl dark:text-white">{{ $heading }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-gray-700 dark:text-gray-300">{{ $lead }}</p>
        <div class="mt-7 flex flex-wrap gap-3">
            <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 font-semibold text-white hover:bg-brand-700 focus-ring">Book a demo</a>
            <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-900 hover:bg-gray-50 focus-ring dark:border-gray-700 dark:text-white dark:hover:bg-gray-800">See pricing</a>
        </div>
    </section>

    {{ $slot }}

    @isset($related)
        <section class="border-t border-gray-200 dark:border-gray-800" aria-label="Read more">
            <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Read more</h2>
                <ul class="mt-4 space-y-3">{{ $related }}</ul>
            </div>
        </section>
    @endisset

    <section class="border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
        <div class="mx-auto max-w-3xl px-4 py-14 text-center sm:px-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">See it on your own books</h2>
            <p class="mt-3 text-gray-700 dark:text-gray-300">
                Book a demo and we will go through MyBooks with you, with your own figures, before you choose anything.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('contact', ['subject' => 'demo']) }}#contact-form" class="inline-flex items-center rounded-lg bg-brand-600 px-5 py-3 font-semibold text-white hover:bg-brand-700 focus-ring">Book a demo</a>
                <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-5 py-3 font-semibold text-gray-900 hover:bg-white focus-ring dark:border-gray-700 dark:text-white dark:hover:bg-gray-800">See pricing</a>
            </div>
        </div>
    </section>
</x-public.layout>
