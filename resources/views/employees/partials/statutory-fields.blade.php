{{-- Statutory details used by the remittance schedules (PAYE by state, pension by PFA, NHF). --}}
@php($employee = $employee ?? null)
<div class="mb-8">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        Statutory Details (PAYE, Pension, NHF)
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <x-field name="tax_state_id" label="State of tax residence (PAYE goes to this state's IRS)" type="select">
                <option value="">Not set</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) old('tax_state_id', $employee?->tax_state_id) === (string) $state->id)>{{ $state->name }}</option>
                @endforeach
            </x-field>
        </div>
        <div>
            <x-field name="pension_fund_administrator_id" label="Pension Fund Administrator (PFA)" type="select">
                <option value="">Not set</option>
                @foreach($pfas as $pfa)
                    <option value="{{ $pfa->id }}" @selected((string) old('pension_fund_administrator_id', $employee?->pension_fund_administrator_id) === (string) $pfa->id)>{{ $pfa->name }}</option>
                @endforeach
            </x-field>
        </div>
        <div>
            <x-field name="rsa_pin" label="RSA PIN" :value="old('rsa_pin', $employee?->rsa_pin)" placeholder="PEN100123456789" maxlength="20" help="PEN followed by 12 digits." />
        </div>
        <div>
            <x-field name="nhf_number" label="NHF number" :value="old('nhf_number', $employee?->nhf_number)" maxlength="30" />
        </div>
    </div>
</div>
