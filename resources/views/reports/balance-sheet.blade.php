<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Balance Sheet') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <x-report-export-buttons 
                    report-type="balance-sheet" 
                    :filters="['as_of' => $asOf]" 
                />
                <a href="{{ route('reports.index') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Reports
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <form method="GET" action="{{ route('reports.balance-sheet') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="as_of" class="block text-sm font-medium text-gray-700 dark:text-gray-300">As Of Date</label>
                        <input type="date" name="as_of" id="as_of" value="{{ $asOf }}" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Assets</dt>
                                <dd class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ number_format($totalAssets, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Liabilities</dt>
                                <dd class="text-lg font-semibold text-red-600 dark:text-red-400">{{ number_format($totalLiabilities, 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 {{ $totalEquity >= 0 ? 'bg-green-500' : 'bg-red-500' }} rounded-md p-3">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div class="ml-5">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Equity</dt>
                                <dd class="text-lg font-semibold {{ $totalEquity >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($totalEquity, 2) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Sheet Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Assets -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assets</h3>
                        <div class="flex-shrink-0 bg-blue-500 rounded-md p-2">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Current Assets -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2 uppercase tracking-wider">Current Assets</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                <!-- Cash & Bank -->
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Cash & Bank
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($cashAndBank, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($assetDetails['cash'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Accounts Receivable -->
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Accounts Receivable
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($accountsReceivable, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($assetDetails['accounts_receivable'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Inventory -->
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Inventory
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($inventory, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($assetDetails['inventory'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Other Current Assets -->
                                @if($otherCurrentAssets != 0 || (isset($assetDetails['other_current']) && $assetDetails['other_current']->count() > 0))
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Other Current Assets
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($otherCurrentAssets, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($assetDetails['other_current'] ?? [] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Total Current Assets -->
                                <div class="flex justify-between text-sm pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Total Current Assets</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($totalCurrentAssets, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Fixed Assets -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2 uppercase tracking-wider">Fixed Assets</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Property, Plant & Equipment
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($fixedAssets, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($assetDetails['fixed'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Total Fixed Assets -->
                                <div class="flex justify-between text-sm pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Total Fixed Assets</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($fixedAssets, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Assets -->
                        <div class="border-t-2 border-blue-500 pt-4">
                            <div class="flex justify-between">
                                <span class="text-base font-bold text-gray-900 dark:text-white">TOTAL ASSETS</span>
                                <span class="text-base font-bold text-blue-600 dark:text-blue-400">{{ number_format($totalAssets, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liabilities & Equity -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Liabilities & Equity</h3>
                        <div class="flex-shrink-0 bg-purple-500 rounded-md p-2">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Current Liabilities -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2 uppercase tracking-wider">Current Liabilities</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                <!-- Accounts Payable -->
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Accounts Payable
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($accountsPayable, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($liabilityDetails['accounts_payable'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Credit Card Payable -->
                                @if($creditCardPayable != 0 || (isset($liabilityDetails['credit_card']) && $liabilityDetails['credit_card']->count() > 0))
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Credit Card Payable
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($creditCardPayable, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($liabilityDetails['credit_card'] ?? [] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Other Current Liabilities -->
                                @if($otherCurrentLiabilities != 0 || (isset($liabilityDetails['other_current']) && $liabilityDetails['other_current']->count() > 0))
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Other Current Liabilities
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($otherCurrentLiabilities, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($liabilityDetails['other_current'] ?? [] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Total Current Liabilities -->
                                <div class="flex justify-between text-sm pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Total Current Liabilities</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($totalCurrentLiabilities, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Long-term Liabilities -->
                        @if($longTermLiabilities != 0 || (isset($liabilityDetails['long_term']) && $liabilityDetails['long_term']->count() > 0))
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2 uppercase tracking-wider">Long-term Liabilities</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Long-term Debt
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($longTermLiabilities, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($liabilityDetails['long_term'] ?? [] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Total Liabilities -->
                        <div class="border-t border-red-300 dark:border-red-700 pt-3">
                            <div class="flex justify-between">
                                <span class="text-sm font-bold text-gray-800 dark:text-gray-200">Total Liabilities</span>
                                <span class="text-sm font-bold text-red-600 dark:text-red-400">{{ number_format($totalLiabilities, 2) }}</span>
                            </div>
                        </div>

                        <!-- Equity -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2 uppercase tracking-wider">Equity</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                <!-- Owner's Capital -->
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Owner's Capital
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($ownersEquity, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($equityDetails['capital'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Retained Earnings -->
                                <div x-data="{ open: false }">
                                    <div class="flex justify-between text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 -mx-2 px-2 py-1 rounded" @click="open = !open">
                                        <span class="text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-1 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                            Retained Earnings
                                        </span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ number_format($retainedEarnings, 2) }}</span>
                                    </div>
                                    <div x-show="open" x-collapse class="ml-5 mt-2 space-y-1 border-l-2 border-gray-200 dark:border-gray-600 pl-3">
                                        @foreach($equityDetails['retained_earnings'] as $account)
                                            <div class="flex justify-between text-xs">
                                                <span class="text-gray-500 dark:text-gray-400">{{ $account->account_code }} - {{ $account->name }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ number_format($account->current_balance, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                @if(abs($priorYearsProfit ?? 0) >= 0.01)
                                <!-- Profit of earlier years not yet closed (A2) -->
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400 pl-5" title="Run the year-end close to move this into Retained Earnings">Earlier Years' Profit (not yet closed)</span>
                                    <span class="font-medium {{ $priorYearsProfit >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($priorYearsProfit, 2) }}
                                    </span>
                                </div>
                                @endif

                                <!-- Net Income (Current Year) -->
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400 pl-5">Net Income (Year from {{ \Carbon\Carbon::parse($fiscalYearStart)->format('j M Y') }})</span>
                                    <span class="font-medium {{ $netIncome >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($netIncome, 2) }}
                                    </span>
                                </div>

                                <!-- Total Equity -->
                                <div class="flex justify-between text-sm pt-2 border-t border-gray-200 dark:border-gray-600">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Total Equity</span>
                                    <span class="font-semibold {{ $totalEquity >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($totalEquity, 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Total Liabilities & Equity -->
                        <div class="border-t-2 border-purple-500 pt-4">
                            <div class="flex justify-between">
                                <span class="text-base font-bold text-gray-900 dark:text-white">TOTAL LIABILITIES & EQUITY</span>
                                <span class="text-base font-bold text-purple-600 dark:text-purple-400">{{ number_format($totalLiabilitiesAndEquity, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Check -->
        @php
            $isBalanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;
        @endphp
        
        @if($isBalanced)
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800 dark:text-green-200">Balance Sheet is Balanced</h3>
                        <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                            Total Assets ({{ number_format($totalAssets, 2) }}) = Total Liabilities & Equity ({{ number_format($totalLiabilitiesAndEquity, 2) }})
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Balance Sheet Out of Balance</h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                            There is a discrepancy of {{ number_format(abs($totalAssets - $totalLiabilitiesAndEquity), 2) }} between Assets ({{ number_format($totalAssets, 2) }}) and Liabilities & Equity ({{ number_format($totalLiabilitiesAndEquity, 2) }}).
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Report Info -->
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Report generated as of: <span class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($asOf)->format('F d, Y') }}</span>
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Click on any category to expand and see individual account balances.
            </p>
        </div>
    </div>
</x-app-layout>
