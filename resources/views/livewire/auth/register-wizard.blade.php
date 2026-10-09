{{--
    Sign-up page (one short form). The left panel shows who we are and the
    plan being chosen; the right panel is the form. Address, logo and the
    rest of the business details are filled in later under Settings.
--}}
@php
    $plan = $this->selectedPlan;
    $price = $plan ? $plan->getPriceForCycle($billing_cycle) : 0;
    $per = $billing_cycle === 'annual' ? 'year' : 'month';
    $field = 'block w-full h-11 rounded-md border-gray-300 bg-white px-3 text-[15px] text-gray-900 placeholder-gray-400 shadow-sm focus:border-brand-600 focus:ring-brand-600';
    $bad = 'border-red-600 focus:border-red-600 focus:ring-red-600';
    $label = 'block text-sm font-medium text-gray-800 mb-1.5';
    $errorCount = $errors->count();
@endphp

<div class="min-h-screen lg:grid lg:grid-cols-12">
    {{-- Left: brand panel (desktop) --}}
    <aside class="hidden lg:block lg:col-span-5 bg-brand-900 text-white">
    <div class="sticky top-0 flex h-screen flex-col justify-between px-12 xl:px-16 py-12">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-3 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-300" aria-label="MyBooks home">
            <x-brand-mark variant="reversed" class="h-10 w-10" />
            <span class="text-2xl font-semibold tracking-tight">MyBooks</span>
        </a>

        <div class="max-w-md">
            <h2 class="text-3xl font-semibold leading-tight">Set up your books in a few minutes.</h2>
            <p class="mt-4 text-brand-200 leading-relaxed">Bookkeeping and accounts made for Nigerian businesses.</p>

            <ul class="mt-8 space-y-3 text-brand-100">
                @foreach (['Invoices, bills, stock and payroll in one place', 'Naira, VAT, PAYE and withholding tax built in', 'Works on your phone as well as your computer'] as $point)
                    <li class="flex gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-none text-accent-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                        <span>{{ $point }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($plan)
                <div class="mt-10 rounded-lg border border-brand-700 bg-brand-800/60 p-5" aria-live="polite">
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-300">Your plan</p>
                    <div class="mt-2 flex items-baseline justify-between gap-4">
                        <p class="text-lg font-semibold">{{ $plan->name }}</p>
                        <p class="text-lg font-semibold whitespace-nowrap">₦{{ number_format($price) }}<span class="text-sm font-normal text-brand-200"> / {{ $per }}</span></p>
                    </div>
                    @if ($plan->features)
                        <ul class="mt-3 space-y-1.5 text-sm text-brand-100">
                            @foreach (array_slice($plan->features, 0, 4) as $feature)
                                <li class="flex gap-2"><span class="text-accent-400" aria-hidden="true">·</span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        <p class="text-sm text-brand-300">
            Questions? <a href="{{ route('contact') }}" class="text-white underline underline-offset-2 hover:text-brand-100">Talk to us</a>
        </p>
    </div>
    </aside>

    {{-- Right: the form --}}
    <main class="lg:col-span-7 xl:col-span-7 flex flex-col min-h-screen">
        <header class="flex items-center justify-between gap-4 px-5 sm:px-10 py-5">
            <a href="{{ url('/') }}" class="lg:invisible inline-flex items-center gap-2" aria-label="MyBooks home">
                <x-brand-mark class="h-8 w-8" />
                <span class="text-lg font-semibold text-brand-900">MyBooks</span>
            </a>
            <p class="text-sm text-gray-600">
                <span class="hidden sm:inline">Already have an account?</span>
                <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:text-brand-900 underline-offset-2 hover:underline">Sign in</a>
            </p>
        </header>

        <div class="flex-1 px-5 sm:px-10 pb-12">
            <div class="mx-auto w-full max-w-lg pt-2 sm:pt-6">
                <h1 class="text-2xl sm:text-[28px] font-semibold tracking-tight text-gray-900">Create your account</h1>
                <p class="mt-2 text-gray-600">It takes about two minutes. You can add your address and logo later.</p>

                @if ($errorCount > 0)
                    <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ $errorCount === 1 ? 'Please fix the item marked below.' : "Please fix the {$errorCount} items marked below." }}
                    </div>
                @endif

                <form wire:submit="register" class="mt-8 space-y-8" novalidate>
                    {{-- Bots fill this in; people never see it. --}}
                    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label for="hp_check">Leave this empty</label>
                        <input type="text" id="hp_check" wire:model="hp_check" tabindex="-1" autocomplete="off">
                    </div>

                    {{-- About you --}}
                    <div class="space-y-5">
                        <div>
                            <label for="name" class="{{ $label }}">Full name</label>
                            <input id="name" type="text" wire:model.live.blur="name" autocomplete="name" autofocus enterkeyhint="next" required
                                class="{{ $field }} @error('name') {{ $bad }} @enderror"
                                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name') <p id="name-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="email" class="{{ $label }}">Work email</label>
                            <input id="email" type="email" wire:model.live.blur="email" autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false" enterkeyhint="next" required
                                class="{{ $field }} @error('email') {{ $bad }} @enderror"
                                aria-describedby="email-help @error('email') email-error @enderror" @error('email') aria-invalid="true" @enderror>
                            @error('email')
                                <p id="email-error" class="mt-1.5 text-sm text-red-700">
                                    {{ $message }}
                                    @if (str_contains($message, 'already uses'))
                                        <a href="{{ route('login') }}" class="font-semibold underline">Sign in</a> or
                                        <a href="{{ route('password.request') }}" class="font-semibold underline">reset your password</a>.
                                    @endif
                                </p>
                            @else
                                <p id="email-help" class="mt-1.5 text-sm text-gray-600">We'll send a link to confirm it.</p>
                            @enderror
                        </div>

                        <div x-data="{ show: false, pw: '' }">
                            <label for="password" class="{{ $label }}">Password</label>
                            <div class="relative">
                                <input id="password" :type="show ? 'text' : 'password'" type="password" wire:model.live.blur="password" x-on:input="pw = $event.target.value"
                                    autocomplete="new-password" enterkeyhint="next" required
                                    class="{{ $field }} pr-20 @error('password') {{ $bad }} @enderror"
                                    aria-describedby="password-rules @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                                <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 px-3 text-sm font-medium text-brand-700 hover:text-brand-900 focus:outline-none focus-visible:underline"
                                    :aria-pressed="show.toString()" aria-controls="password">
                                    <span x-text="show ? 'Hide' : 'Show'">Show</span>
                                </button>
                            </div>
                            @error('password') <p id="password-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            <ul id="password-rules" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm" aria-label="Password needs">
                                @foreach ([
                                    ['pw.length >= 8', '8 or more characters'],
                                    ['/[a-z]/.test(pw) && /[A-Z]/.test(pw)', 'Upper and lower case'],
                                    ['/[0-9]/.test(pw)', 'A number'],
                                    ['/[^A-Za-z0-9]/.test(pw)', 'A symbol, like ! or #'],
                                ] as [$test, $text])
                                    <li class="flex items-center gap-1.5" :class="({{ $test }}) ? 'text-green-700' : 'text-gray-600'">
                                        <svg class="h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path x-show="{{ $test }}" fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
                                            <circle x-show="!({{ $test }})" cx="10" cy="10" r="3"/>
                                        </svg>
                                        <span>{{ $text }}</span><span class="sr-only" x-text="({{ $test }}) ? ' (done)' : ''"></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div>
                            <label for="phone" class="{{ $label }}">Phone number <span class="font-normal text-gray-600">(optional)</span></label>
                            <input id="phone" type="tel" wire:model.live.blur="phone" autocomplete="tel" inputmode="tel" placeholder="0803 123 4567" enterkeyhint="next"
                                class="{{ $field }} @error('phone') {{ $bad }} @enderror"
                                @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone') <p id="phone-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Your business --}}
                    <div class="space-y-5 border-t border-gray-200 pt-8" role="group" aria-labelledby="business-heading">
                        <h2 id="business-heading" class="text-base font-semibold text-gray-900">Your business</h2>

                        <div class="grid gap-5 sm:grid-cols-5">
                            <div class="sm:col-span-3">
                                <label for="company_name" class="{{ $label }}">Business name</label>
                                <input id="company_name" type="text" wire:model.live.blur="company_name" autocomplete="organization" enterkeyhint="next" required
                                    class="{{ $field }} @error('company_name') {{ $bad }} @enderror"
                                    @error('company_name') aria-invalid="true" aria-describedby="company_name-error" @enderror>
                                @error('company_name') <p id="company_name-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="currency" class="{{ $label }}">Currency</label>
                                <select id="currency" wire:model="currency" class="{{ $field }} pr-8">
                                    @foreach ($currencies as $code => $text)
                                        <option value="{{ $code }}">{{ $text }}</option>
                                    @endforeach
                                </select>
                                @error('currency') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                <input type="checkbox" wire:model.live="separate_company_email" class="mt-0.5 h-4 w-4 rounded border-gray-400 text-brand-600 focus:ring-brand-600">
                                <span>Use a different email on invoices <span class="text-gray-600">(for example accounts@yourbusiness.com)</span></span>
                            </label>
                            @if ($separate_company_email)
                                <div class="mt-4">
                                    <label for="company_email" class="{{ $label }}">Business email</label>
                                    <input id="company_email" type="email" wire:model.live.blur="company_email" autocomplete="off" inputmode="email" autocapitalize="off" spellcheck="false"
                                        class="{{ $field }} @error('company_email') {{ $bad }} @enderror"
                                        @error('company_email') aria-invalid="true" aria-describedby="company_email-error" @enderror>
                                    @error('company_email') <p id="company_email-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                                </div>
                            @elseif ($errors->has('company_email') && ! $errors->has('email'))
                                <p class="mt-1.5 text-sm text-red-700">{{ $errors->first('company_email') }} Tick the box to use a different email on invoices.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Plan --}}
                    @if ($this->plans->isNotEmpty())
                        <fieldset class="border-t border-gray-200 pt-8">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <legend class="text-base font-semibold text-gray-900">Plan</legend>
                                @if ($plan && $plan->allow_monthly_billing && $plan->allow_annual_billing)
                                    <div class="inline-flex rounded-md border border-gray-300 p-0.5 text-sm" role="radiogroup" aria-label="Billing">
                                        @foreach (['monthly' => 'Monthly', 'annual' => 'Yearly'] as $cycle => $text)
                                            <label class="cursor-pointer rounded px-3 py-1.5 font-medium has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-600 {{ $billing_cycle === $cycle ? 'bg-brand-600 text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                                                <input type="radio" wire:model.live="billing_cycle" value="{{ $cycle }}" class="sr-only">
                                                {{ $text }}
                                                @if ($cycle === 'annual' && $plan->annual_savings_percent > 0)
                                                    <span class="{{ $billing_cycle === 'annual' ? 'text-brand-100' : 'text-green-700' }}">· save {{ round($plan->annual_savings_percent) }}%</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($plan)
                                    <p class="text-sm text-gray-600">Billed {{ $plan->allow_annual_billing ? 'yearly' : 'monthly' }}</p>
                                @endif
                            </div>

                            <div class="mt-4 divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-300">
                                @foreach ($this->plans as $p)
                                    @php
                                        $cycle = $p->allowsBillingCycle($billing_cycle) ? $billing_cycle : ($p->allow_monthly_billing ? 'monthly' : 'annual');
                                        $chosen = $plan_id === $p->id;
                                    @endphp
                                    <label wire:key="plan-{{ $p->id }}" class="flex cursor-pointer items-start gap-3 px-4 py-4 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-inset has-[:focus-visible]:ring-brand-600 {{ $chosen ? 'bg-brand-50' : 'bg-white hover:bg-gray-50' }}">
                                        <input type="radio" name="plan" wire:model.live="plan_id" value="{{ $p->id }}" class="mt-1 h-4 w-4 border-gray-400 text-brand-600 focus:ring-brand-600">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-gray-900">{{ $p->name }}</span>
                                                @if ($p->slug === 'professional')
                                                    <span class="badge badge-accent">Recommended</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block text-sm text-gray-600">{{ $p->description }}{{ $p->max_users ? ' · up to '.$p->max_users.' users' : '' }}</span>
                                        </span>
                                        <span class="text-right">
                                            <span class="block font-semibold text-gray-900 whitespace-nowrap">₦{{ number_format($p->getPriceForCycle($cycle)) }}</span>
                                            <span class="block text-xs text-gray-600">per {{ $cycle === 'annual' ? 'year' : 'month' }}{{ $cycle !== $billing_cycle ? ', yearly only' : '' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('plan_id') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            @error('billing_cycle') <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p> @enderror
                            <p class="mt-3 text-sm text-gray-600">You can change plan later in Settings.</p>
                        </fieldset>
                    @else
                        <div class="rounded-md border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900" role="status">
                            Sign-up is paused while we update our plans. Please <a href="{{ route('contact') }}" class="font-semibold underline">contact us</a> and we'll set up your account.
                        </div>
                    @endif

                    {{-- Submit --}}
                    <div class="border-t border-gray-200 pt-8">
                        @if ($plan)
                            <div class="mb-5 flex items-baseline justify-between gap-4 rounded-md bg-gray-50 px-4 py-3">
                                <span class="text-sm text-gray-700">{{ $plan->name }}, billed {{ $billing_cycle === 'annual' ? 'yearly' : 'monthly' }}</span>
                                <span class="font-semibold text-gray-900 whitespace-nowrap">₦{{ number_format($price) }} / {{ $per }}</span>
                            </div>
                            @if ($price > 0)
                                <p class="-mt-2 mb-5 text-sm text-gray-600">Nothing is charged now. You'll pay after you confirm your email.</p>
                            @endif
                        @endif

                        <button type="submit" @disabled($this->plans->isEmpty())
                            class="relative flex h-12 w-full items-center justify-center rounded-md bg-brand-600 px-6 text-base font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            wire:loading.attr="disabled" wire:target="register">
                            <span wire:loading.remove wire:target="register">Create account</span>
                            <span wire:loading.flex wire:target="register" class="items-center gap-2">
                                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                                Creating your account…
                            </span>
                        </button>

                        <p class="mt-4 text-center text-sm text-gray-600">
                            By creating an account you agree to our
                            <a href="{{ route('terms-of-service') }}" target="_blank" rel="noopener" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">Terms of Service</a>
                            and
                            <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">Privacy Policy</a>.
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <footer class="px-5 sm:px-10 py-6 text-center text-sm text-gray-600 lg:text-left">
            &copy; {{ date('Y') }} MyBooks
        </footer>
    </main>
</div>
