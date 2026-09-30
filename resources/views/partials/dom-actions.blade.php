{{--
    Small page behaviours without inline event handlers.

    The content security policy blocks onclick="...", onsubmit="..." and
    onchange="..." attributes, so buttons using them did nothing (finding U1).
    Use these data attributes instead:

      data-confirm="Delete this?"     on a form (asks on submit) or a button/link (asks on click)
      data-print                      prints the page
      data-show="id" / data-hide="id" removes / adds the "hidden" class on that element
      data-open-modal="name" / data-close-modal="name"   opens / closes an <x-modal name="name">
      data-set-value="id" data-value="123"   puts a value into an input
      data-submit-closest-form        submits the surrounding form (e.g. logout links)
      data-call="fnName"              calls window.fnName on click (or on change for inputs/selects)
          data-arg="csv"                passes a fixed argument
          data-pass-value               passes the element's current value

    Forms that post (not GET) have their submit buttons disabled while they
    send, so a double click can't save twice (U13). Opt out with
    data-no-disable on the form (e.g. a download that keeps the page open).

    Included once per full page: layouts, and the standalone print pages.
--}}
<script nonce="{{ app('csp-nonce') }}">
(function () {
    if (window.__mbDomActions) { return; }
    window.__mbDomActions = true;

    function isField(el) {
        return el.matches('select, input, textarea');
    }

    function callFn(el, event) {
        var fn = window[el.dataset.call];
        if (typeof fn !== 'function') { return; }
        if ('passValue' in el.dataset) { fn(el.value); }
        else if ('arg' in el.dataset) { fn(el.dataset.arg); }
        else { fn.call(el, event); }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.matches && form.matches('form[data-confirm]') && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    // Disable submit buttons once a posting form is really sent (U13). This
    // runs in the bubbling phase, after data-confirm and any page handler, so
    // a cancelled or JavaScript-handled submit is left alone. Disabling waits
    // a tick so the clicked button's name/value is still sent.
    var SUBMITS = 'button[type=submit], button:not([type]), input[type=submit]';

    function setBusy(form, busy) {
        form.querySelectorAll(SUBMITS).forEach(function (button) {
            if (busy && !button.disabled) {
                button.disabled = true;
                button.setAttribute('data-busy', '');
            } else if (!busy && button.hasAttribute('data-busy')) {
                button.disabled = false;
                button.removeAttribute('data-busy');
            }
        });
        form.toggleAttribute('aria-busy', busy);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (event.defaultPrevented || !form.matches || !form.matches('form')) { return; }
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') { return; }
        if (form.hasAttribute('data-no-disable') || form.target === '_blank') { return; }

        if (form.hasAttribute('data-submitting')) {
            event.preventDefault(); // second click while the first is sending
            return;
        }
        form.setAttribute('data-submitting', '');
        setTimeout(function () { setBusy(form, true); }, 0);
        // Downloads leave the page in place: free the form again after a while.
        setTimeout(function () {
            form.removeAttribute('data-submitting');
            setBusy(form, false);
        }, 15000);
    });

    // Coming back with the browser's Back button restores a frozen page.
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) { return; }
        document.querySelectorAll('form[data-submitting]').forEach(function (form) {
            form.removeAttribute('data-submitting');
            setBusy(form, false);
        });
    });

    document.addEventListener('click', function (event) {
        var el = event.target.closest && event.target.closest('[data-confirm]:not(form), [data-print], [data-show], [data-hide], [data-set-value], [data-submit-closest-form], [data-call], [data-open-modal], [data-close-modal]');
        if (!el) { return; }

        if (el.matches('[data-confirm]:not(form)') && !window.confirm(el.dataset.confirm)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        if ('print' in el.dataset) {
            event.preventDefault();
            window.print();
        }
        if (el.dataset.show) {
            var shown = document.getElementById(el.dataset.show);
            if (shown) { shown.classList.remove('hidden'); }
        }
        if (el.dataset.hide) {
            var hidden = document.getElementById(el.dataset.hide);
            if (hidden) { hidden.classList.add('hidden'); }
        }
        if (el.dataset.openModal) {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: el.dataset.openModal }));
        }
        if (el.dataset.closeModal) {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: el.dataset.closeModal }));
        }
        if (el.dataset.setValue) {
            var input = document.getElementById(el.dataset.setValue);
            if (input) {
                input.value = el.dataset.value;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        if ('submitClosestForm' in el.dataset) {
            event.preventDefault();
            var form = el.closest('form');
            if (form) { form.requestSubmit ? form.requestSubmit() : form.submit(); }
        }
        if (el.dataset.call && !isField(el)) {
            callFn(el, event);
        }
    }, true); // capture: ask before Livewire or other handlers on the button run

    document.addEventListener('change', function (event) {
        var el = event.target;
        if (el.dataset && el.dataset.call && isField(el)) {
            callFn(el, event);
        }
    });
})();
</script>
