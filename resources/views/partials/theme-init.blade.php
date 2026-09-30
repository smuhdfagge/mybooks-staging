{{--
    Set dark mode before the page paints (U12).

    Runs in the <head>, before the stylesheet applies, so the page no longer
    flashes light and then turns dark once Alpine starts. A saved choice
    wins; with no saved choice the device setting is used.

    @include('partials.theme-init')                        uses the "dark" key
    @include('partials.theme-init', ['key' => 'adminDark'])
--}}
<script nonce="{{ app('csp-nonce') }}" data-theme-init>
(function () {
    var key = @js($key ?? 'dark');
    var saved = null;
    try { saved = window.localStorage.getItem(key); } catch (e) {}
    var dark = saved === null
        ? !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
        : saved === 'true';
    document.documentElement.classList.toggle('dark', dark);
})();
</script>
