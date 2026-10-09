<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Employee') }}: {{ $employee->full_name }}
            </h2>
            <a href="{{ route('employees.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('employees.update', $employee) }}" method="POST" class="p-6" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Basic Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Basic Information
                        </h3>

                        <!-- Photo Upload -->
                        <div class="mb-6 flex items-center gap-4">
                            <div class="h-20 w-20 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden" x-data="{ preview: null }">
                                @if($employee->photo_path)
                                    <img x-show="!preview" src="{{ asset('storage/' . $employee->photo_path) }}" class="h-20 w-20 rounded-full object-cover" alt="{{ $employee->first_name }}">
                                @else
                                    <svg x-show="!preview" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                @endif
                                <img x-show="preview" :src="preview" class="h-20 w-20 rounded-full object-cover" alt="Photo preview" x-cloak>
                                <input type="file" name="photo" accept="image/*" class="hidden" id="photo-upload"
                                    @change="const file = $event.target.files[0]; if(file) { const reader = new FileReader(); reader.onload = e => preview = e.target.result; reader.readAsDataURL(file); }" @error('photo') aria-invalid="true" aria-describedby="photo-error" @enderror>
                            </div>
                            <div>
                                <label for="photo-upload" class="cursor-pointer inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                    {{ $employee->photo_path ? 'Change Photo' : 'Upload Photo' }}
                                </label>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">JPG, PNG or GIF. Max 2MB.</p>
                                @error('photo') <p id="photo-error" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="employee_id" class="form-label">Employee ID</label>
                                <input type="text" name="employee_id" id="employee_id" value="{{ $employee->employee_id }}" readonly
                                    class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400 shadow-sm bg-gray-100">
                            </div>

                            <div>
                                <x-field name="first_name" label="First Name" :value="old('first_name', $employee->first_name)" required />
                            </div>

                            <div>
                                <x-field name="last_name" label="Last Name" :value="old('last_name', $employee->last_name)" required />
                            </div>

                            <div>
                                <x-field name="email" label="Email Address" type="email" :value="old('email', $employee->email)" />
                            </div>

                            <div>
                                <x-field name="phone" label="Phone Number" :value="old('phone', $employee->phone)" />
                            </div>

                            <div>
                                <x-field name="date_of_birth" label="Date of Birth" type="date" :value="old('date_of_birth', $employee->date_of_birth?->format('Y-m-d'))" />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="gender"
                                    label="Gender"
                                    :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']"
                                    :value="old('gender', $employee->gender ?? '')"
                                    placeholder="Select Gender"
                                    search-placeholder="Search..."
                                    :has-error="$errors->has('gender')" />
                                @error('gender')
                                    <p id="gender-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="marital_status"
                                    label="Marital Status"
                                    :options="['single' => 'Single', 'married' => 'Married', 'divorced' => 'Divorced', 'widowed' => 'Widowed']"
                                    :value="old('marital_status', $employee->marital_status ?? '')"
                                    placeholder="Select Status"
                                    search-placeholder="Search..."
                                    :has-error="$errors->has('marital_status')" />
                                @error('marital_status')
                                    <p id="marital_status-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Employment Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Employment Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-searchable-select
                                    name="department_id"
                                    label="Department"
                                    :options="$departments->pluck('name', 'id')->toArray()"
                                    :value="old('department_id', (string)($employee->department_id ?? ''))"
                                    placeholder="Select Department"
                                    search-placeholder="Search departments..."
                                    :has-error="$errors->has('department_id')" />
                                @error('department_id')
                                    <p id="department_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="designation_id"
                                    label="Designation"
                                    :options="$designations->pluck('name', 'id')->toArray()"
                                    :value="old('designation_id', (string)($employee->designation_id ?? ''))"
                                    placeholder="Select Designation"
                                    search-placeholder="Search designations..."
                                    :has-error="$errors->has('designation_id')" />
                                @error('designation_id')
                                    <p id="designation_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="hire_date" label="Hire Date" type="date" :value="old('hire_date', $employee->hire_date?->format('Y-m-d'))" required />
                            </div>

                            <div>
                                <x-field name="termination_date" label="Termination Date" type="date" :value="old('termination_date', $employee->termination_date?->format('Y-m-d'))" />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="employment_type"
                                    label="Employment Type"
                                    :options="['full-time' => 'Full-time', 'part-time' => 'Part-time', 'contract' => 'Contract', 'intern' => 'Intern']"
                                    :value="old('employment_type', $employee->employment_type ?? '')"
                                    placeholder="Select Type"
                                    search-placeholder="Search..."
                                    :has-error="$errors->has('employment_type')" />
                                @error('employment_type')
                                    <p id="employment_type-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="status"
                                    label="Status"
                                    :options="['active' => 'Active', 'on-leave' => 'On Leave', 'terminated' => 'Terminated', 'resigned' => 'Resigned']"
                                    :value="old('status', $employee->status ?? 'active')"
                                    placeholder="Select Status"
                                    search-placeholder="Search..."
                                    :has-error="$errors->has('status')" />
                                @error('status')
                                    <p id="status-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="salary_structure_id"
                                    label="Salary Structure"
                                    :options="$salaryStructures->pluck('name', 'id')->toArray()"
                                    :value="old('salary_structure_id', (string)($employee->salary_structure_id ?? ''))"
                                    placeholder="Select Salary Structure"
                                    search-placeholder="Search structures..."
                                    :has-error="$errors->has('salary_structure_id')" />
                                @error('salary_structure_id')
                                    <p id="salary_structure_id-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Address Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <x-field name="address" label="Address" type="textarea" :value="old('address', $employee->address)" rows="2" />
                            </div>

                            <div>
                                <x-field name="city" label="City" :value="old('city', $employee->city)" />
                            </div>

                            <div>
                                <x-searchable-select
                                    name="state"
                                    label="State / Province"
                                    :options="$states->pluck('name')->toArray()"
                                    :value="old('state', $employee->state ?? '')"
                                    placeholder="Select State"
                                    search-placeholder="Search states..."
                                    :has-error="$errors->has('state')" />
                                @error('state')
                                    <p id="state-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-searchable-select
                                    name="country"
                                    label="Country"
                                    :options="$countries->pluck('name')->toArray()"
                                    :value="old('country', $employee->country ?? '')"
                                    placeholder="Select Country"
                                    search-placeholder="Search countries..."
                                    :has-error="$errors->has('country')" />
                                @error('country')
                                    <p id="country-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <x-field name="postal_code" label="Postal Code" :value="old('postal_code', $employee->postal_code)" />
                            </div>
                        </div>
                    </div>

                    <!-- Banking Information -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Banking Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="bank_name" label="Bank Name" :value="old('bank_name', $employee->bank_name)" />
                            </div>

                            <div>
                                <x-field name="bank_account_number" label="Account Number" :value="old('bank_account_number', $employee->bank_account_number)" />
                            </div>

                            <div>
                                <x-field name="bank_routing_number" label="Routing Number" :value="old('bank_routing_number', $employee->bank_routing_number)" />
                            </div>

                            <div>
                                <x-field name="tax_id" label="Tax ID / SSN" :value="old('tax_id', $employee->tax_id)" />
                            </div>
                            <div>
                                <x-field name="annual_rent" label="Annual rent paid (for PAYE rent relief)" type="number" :value="old('annual_rent', $employee->annual_rent)" step="0.01" min="0" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nigeria: 20% of this, up to ₦500,000 a year, comes off pay before PAYE. Keep the tenancy receipt.</p>
                                @error('annual_rent')
                                    <p id="annual_rent-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Statutory contributions (tax pack 1) -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">PAYE, pension and NHF</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-searchable-select
                                    name="tax_state"
                                    label="State of residence (for PAYE)"
                                    :options="$states->pluck('name')->toArray()"
                                    :value="old('tax_state', $employee->tax_state) ?? ''"
                                    placeholder="Same as the business"
                                    search-placeholder="Search states..."
                                    :has-error="$errors->has('tax_state')" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">PAYE goes to this state's Internal Revenue Service. Left empty: the address state, then the business's state.</p>
                                @error('tax_state')
                                    <p id="tax_state-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <x-field name="pfa_name" label="Pension fund administrator (PFA)" :value="old('pfa_name', $employee->pfa_name)" maxlength="150" />
                            </div>
                            <div>
                                <x-field name="rsa_pin" label="RSA PIN" :value="old('rsa_pin', $employee->rsa_pin)" maxlength="30" help="Retirement Savings Account PIN, e.g. PEN100000000000." />
                            </div>
                            <div>
                                <x-field name="nhf_number" label="NHF number" :value="old('nhf_number', $employee->nhf_number)" maxlength="30" />
                                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="hidden" name="nhf_registered" value="0">
                                    <input type="checkbox" name="nhf_registered" value="1" @checked(old('nhf_registered', $employee->nhf_registered)) class="rounded border-gray-300 dark:border-gray-600">
                                    Deduct NHF (registered with the National Housing Fund)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            Emergency Contact
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-field name="emergency_contact_name" label="Contact Name" :value="old('emergency_contact_name', $employee->emergency_contact_name)" />
                            </div>

                            <div>
                                <x-field name="emergency_contact_phone" label="Contact Phone" :value="old('emergency_contact_phone', $employee->emergency_contact_phone)" />
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Additional Notes
                        </h3>
                        <div>
                            <textarea name="notes" id="notes" rows="3" aria-label="Additional notes"
                                class="form-control @error('notes') border-red-500 @enderror" @error('notes') aria-invalid="true" aria-describedby="notes-error" @enderror>{{ old('notes', $employee->notes) }}</textarea>
                            @error('notes')
                                <p id="notes-error" class="mt-1 text-sm text-red-600 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('employees.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
