{{-- Public footer (home page plan H2, from the sample design). --}}
@php
    $home = route('home');
    $cols = [
        'Product' => [['What it does', $home.'#tour'], ['Nigerian tax', $home.'#rules'], ['Pricing', route('pricing')]],
        'Learn' => [['Guides', route('guides.index')], ['VAT returns in Nigeria', route('guides.show', 'vat-returns-nigeria')]],
        'Company' => [['About', route('about')], ['Contact', route('contact')], ['Support', route('support')]],
        'Legal' => [['Privacy policy', route('privacy-policy')], ['Terms of service', route('terms-of-service')]],
    ];
    if (Route::has('login')) {
        $cols['Product'][] = ['Sign in', route('login')];
    }
@endphp

<footer class="bg-brand-900 pb-7 pt-14 text-[0.92rem] text-brand-200">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-[2fr_1fr_1fr_1fr]">
            <div class="col-span-2 md:col-span-1">
                <a href="{{ $home }}" class="mb-3 inline-flex items-center gap-2.5 rounded text-xl font-semibold text-white focus-ring">
                    <x-brand-mark class="h-[30px] w-[30px]" /><span>MyBooks</span>
                </a>
                <p class="max-w-xs leading-relaxed">Accounting software for Nigerian businesses. Prices in naira.</p>
            </div>
            @foreach ($cols as $heading => $items)
                <div>
                    <h2 class="mb-3 text-[0.95rem] font-semibold text-white">{{ $heading }}</h2>
                    <ul class="space-y-1">
                        @foreach ($items as [$label, $href])
                            <li><a href="{{ $href }}" class="inline-block rounded py-1 text-brand-200 hover:text-white focus-ring">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="mt-10 flex flex-wrap justify-between gap-3 border-t border-brand-700 pt-5">
            <span>&copy; {{ now()->year }} MyBooks</span>
            <span>Payments through Paystack</span>
        </div>
    </div>
</footer>
