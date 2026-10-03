{{--
    Warns when a manual journal line uses Accounts Receivable or Accounts
    Payable (session 10). It is still allowed, but the amount won't show on
    any customer's or supplier's statement. $controlAccounts: id => name.
--}}
@if(! empty($controlAccounts))
    <div id="control-account-warning" class="hidden mb-4 rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-700 p-4 text-sm text-amber-800 dark:text-amber-200" role="alert">
        <p class="font-medium">This journal uses <span data-control-names></span>.</p>
        <p class="mt-1">That's allowed, but the amount won't show on any customer's or supplier's statement, and the Receivables &amp; payables check report will list this journal as a difference.
            To change what a customer or supplier owes, use an invoice, bill, credit note or payment instead.</p>
    </div>
    <script nonce="{{ app('csp-nonce') }}">
        (function () {
            const control = @json($controlAccounts);
            function check() {
                const names = [];
                document.querySelectorAll('select[name^="entries["][name$="[account_id]"]').forEach(function (select) {
                    const name = control[select.value];
                    if (name && names.indexOf(name) === -1) { names.push(name); }
                });
                const box = document.getElementById('control-account-warning');
                box.querySelector('[data-control-names]').textContent = names.join(' and ');
                box.classList.toggle('hidden', names.length === 0);
            }
            document.addEventListener('change', function (e) {
                if (e.target.matches('select[name^="entries["]')) { check(); }
            });
            document.addEventListener('DOMContentLoaded', check);
        })();
    </script>
@endif
