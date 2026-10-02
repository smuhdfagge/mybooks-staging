{{--
    Withholding tax on a payment (tax pack 2). Used inside the payment made
    and payment received forms; reads the vendor/customer, bill/invoice and
    amount the form already has. Nothing is sent when the box is unticked.

    @include('withholding-tax._payment-fields', ['side' => 'made'])
--}}
@php
    $whtConfig = \App\Support\WithholdingTax::formConfig(auth()->user()->tenant_id, $side);
    $whtOld = [
        'on' => (bool) old('wht_amount'),
        'rateId' => (string) old('wht_rate_id', ''),
        'rate' => (string) old('wht_rate', ''),
        'amount' => (string) old('wht_amount', ''),
    ];
@endphp
<div class="md:col-span-2 rounded-md border border-gray-200 dark:border-gray-700 p-4" x-data="whtFields(@js($whtConfig), @js($whtOld))"
    @if($side === 'received') x-show="!isDeposit" @endif>
    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
        <input type="checkbox" x-model="whtOn" @change="recalc()" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600">
        {{ $side === 'made' ? 'Deduct withholding tax (WHT) from this payment' : 'The customer deducted withholding tax (WHT)' }}
    </label>

    <div x-show="whtOn" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-3">
            <label for="wht_rate_id" class="form-label">Type of transaction</label>
            <select id="wht_rate_id" name="wht_rate_id" x-model="whtRateId" @change="recalc()" :disabled="!whtOn" class="form-control">
                <option value="">Choose...</option>
                <template x-for="r in cfg.rates" :key="r.id">
                    <option :value="r.id" x-text="r.name + ' (' + rateLabel(r) + ')'"></option>
                </template>
            </select>
        </div>
        <div>
            <label for="wht_rate" class="form-label">Rate %</label>
            <input type="number" id="wht_rate" name="wht_rate" step="0.01" min="0" max="100" x-model="whtRate" @input="whtAmount = calc(whtRate)" :disabled="!whtOn" class="form-control">
        </div>
        <div>
            <label for="wht_amount" class="form-label">WHT amount</label>
            <input type="number" id="wht_amount" name="wht_amount" step="0.01" min="0" x-model="whtAmount" :disabled="!whtOn" class="form-control @error('wht_amount') border-red-500 @enderror">
            @error('wht_amount')<p class="form-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="form-label">{{ $side === 'made' ? 'Vendor receives' : 'Money received' }}</span>
            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="formatMoney(net)"></p>
        </div>
        <p class="sm:col-span-3 text-xs text-gray-500 dark:text-gray-400">
            Worked out on the amount before VAT<span x-show="doc"> (this {{ $side === 'made' ? 'bill' : 'invoice' }}'s VAT is left out)</span>.
            <span x-show="party && party.type === 'individual'">Individual rate used.</span>
            <span x-show="cfg.doubleWithoutTin && party && !party.has_tin" class="text-amber-700 dark:text-amber-400">No TIN on file: the rate is doubled.</span>
            @if($side === 'made')
                <span x-show="exemptHint" class="text-amber-700 dark:text-amber-400">As a small company you may not need to deduct WHT here (supplier has a TIN and the payment is within the monthly limit).</span>
                The total is owed to the tax office; record paying it under Withholding Tax.
            @else
                Ask the customer for the WHT credit note (certificate); you can claim it against your income tax.
            @endif
        </p>
    </div>
</div>

@once
@push('scripts')
<script nonce="{{ app('csp-nonce') }}">
    function whtFields(cfg, old) {
        return {
            cfg: cfg,
            whtOn: old.on,
            whtRateId: old.rateId,
            whtRate: old.rate,
            whtAmount: old.amount,
            payAmount: 0,

            init() {
                const el = document.getElementById('amount');
                const sync = () => { this.payAmount = parseFloat(el.value) || 0; if (this.whtOn && this.whtRateId) this.whtAmount = this.calc(this.whtRate); };
                sync();
                el.addEventListener('input', sync);
                this.$watch(cfg.docKey, () => this.$nextTick(sync));
                this.$watch(cfg.partyKey, () => this.recalc());
            },
            get party() { return cfg.parties[this[cfg.partyKey]] || null; },
            get doc() { return cfg.docs[this[cfg.docKey]] || null; },
            get base() {
                const d = this.doc;
                return d && d.total > 0 ? this.payAmount * (d.total - d.tax) / d.total : this.payAmount;
            },
            get net() { return this.payAmount - (this.whtOn ? (parseFloat(this.whtAmount) || 0) : 0); },
            get exemptHint() { return cfg.smallCompany && this.party && this.party.has_tin && this.payAmount <= cfg.threshold; },
            rateLabel(r) {
                const parts = [];
                if (r.company !== null) parts.push('company ' + r.company + '%');
                if (r.individual !== null) parts.push('individual ' + r.individual + '%');
                return parts.join(', ');
            },
            suggestedRate() {
                const r = cfg.rates.find(x => x.id == this.whtRateId);
                if (!r) return 0;
                let rate = this.party && this.party.type === 'individual' ? r.individual : r.company;
                if (rate === null) return 0;
                if (cfg.doubleWithoutTin && this.party && !this.party.has_tin) rate = rate * 2;
                return rate;
            },
            calc(rate) { return (Math.round(this.base * (parseFloat(rate) || 0)) / 100).toFixed(2); },
            recalc() {
                if (!this.whtOn || !this.whtRateId) return;
                this.whtRate = this.suggestedRate();
                this.whtAmount = this.calc(this.whtRate);
            },
        };
    }
</script>
@endpush
@endonce
