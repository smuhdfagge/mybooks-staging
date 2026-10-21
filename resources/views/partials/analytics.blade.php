{{--
    Google Analytics (GA4), on the public pages only.

    Included from the public layout, so it never runs on a signed-in page:
    what a business does inside its own books is not ours to measure, and
    sending customer page paths to Google would be a different promise from
    the one the privacy policy makes.

    Nothing is written at all unless mybooks.google_analytics_id is set, so
    staging and test copies stay out of the real figures by doing nothing.

    Both tags carry the CSP nonce. The policy is nonce-based with
    'strict-dynamic' (SecurityHeaders), which means a script without the
    nonce is blocked, and anything gtag.js then loads for itself is allowed
    because gtag.js was trusted. SecurityHeaders also widens connect-src and
    img-src for Google's collection addresses when this id is set.
--}}
@php($gaId = config('mybooks.google_analytics_id'))

@if ($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}" nonce="{{ app('csp-nonce') }}"></script>
    <script nonce="{{ app('csp-nonce') }}">
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', '{{ $gaId }}');
    </script>
@endif
