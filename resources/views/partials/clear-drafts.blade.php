{{--
    Unsaved form drafts (x-form-auto-save) are kept in this browser's
    localStorage under "mybooks_draft_…" until the form is saved. They hold
    business data (a half-typed invoice: customer, amounts), so they must
    not outlive the signed-in session on a shared computer.

    @include('partials.clear-drafts')                     in the app layout:
        clears them when a form marked data-clears-drafts is submitted
        (the sign-out forms)
    @include('partials.clear-drafts', ['now' => true])    in the guest layout:
        clears them on the sign-in pages, which is where a session that
        timed out, or was ended elsewhere, lands
--}}
<script nonce="{{ app('csp-nonce') }}" data-clear-drafts>
(function () {
    function clearDrafts() {
        try {
            for (var i = window.localStorage.length - 1; i >= 0; i--) {
                var key = window.localStorage.key(i);
                if (key && key.indexOf('mybooks_draft_') === 0) {
                    window.localStorage.removeItem(key);
                }
            }
        } catch (e) {}
    }
@if ($now ?? false)
    clearDrafts();
@else
    document.addEventListener('submit', function (event) {
        if (event.target && event.target.hasAttribute && event.target.hasAttribute('data-clears-drafts')) {
            clearDrafts();
        }
    }, true);
@endif
})();
</script>
