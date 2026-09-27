<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Tax Compliance Templates') }}
            </h2>
            <a href="{{ route('payroll.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                &larr; Back to Payroll
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Current Tax Brackets --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Current Tax Brackets</h3>
                @if($currentBrackets->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">No tax brackets configured. Apply a template below to get started.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Min</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Max</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rate %</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Period</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($currentBrackets as $bracket)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $bracket->name }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ number_format($bracket->min_amount, 2) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ $bracket->max_amount ? number_format($bracket->max_amount, 2) : '∞' }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ $bracket->rate }}%</td>
                                        <td class="px-4 py-2 text-sm text-center text-gray-900 dark:text-gray-100">{{ ucfirst($bracket->period) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Available Templates --}}
            @foreach($templates as $countryCode => $countryTemplates)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ $countryCode }} Templates
                    </h3>

                    @foreach($countryTemplates as $template)
                        <div class="mb-6 p-4 border border-gray-200 dark:border-gray-700 rounded-lg {{ $template->is_current ? 'ring-2 ring-indigo-500' : '' }}">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $template->name }}
                                        @if($template->is_current)
                                            <span class="ml-2 px-2 py-0.5 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full">Latest</span>
                                        @endif
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $template->description }}</p>
                                </div>
                                <form action="{{ route('payroll.apply-tax-template') }}" method="POST" onsubmit="return confirm('This will replace your current tax brackets. Continue?')">
                                    @csrf
                                    <input type="hidden" name="template_id" value="{{ $template->id }}">
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                                        Apply Template
                                    </button>
                                </form>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-1 text-left text-xs text-gray-500 dark:text-gray-400">Bracket</th>
                                            <th class="px-3 py-1 text-right text-xs text-gray-500 dark:text-gray-400">Min</th>
                                            <th class="px-3 py-1 text-right text-xs text-gray-500 dark:text-gray-400">Max</th>
                                            <th class="px-3 py-1 text-right text-xs text-gray-500 dark:text-gray-400">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($template->brackets as $bracket)
                                            <tr>
                                                <td class="px-3 py-1 text-gray-700 dark:text-gray-300">{{ $bracket['name'] ?? 'Bracket' }}</td>
                                                <td class="px-3 py-1 text-right text-gray-700 dark:text-gray-300">{{ number_format($bracket['min'] ?? 0, 2) }}</td>
                                                <td class="px-3 py-1 text-right text-gray-700 dark:text-gray-300">{{ isset($bracket['max']) ? number_format($bracket['max'], 2) : '∞' }}</td>
                                                <td class="px-3 py-1 text-right text-gray-700 dark:text-gray-300">{{ $bracket['rate'] }}%</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($template->employer_contributions)
                                <div class="mt-3">
                                    <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-1">Default Employer Contributions</h5>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($template->employer_contributions as $contrib)
                                            <span class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded">
                                                {{ $contrib['name'] }}: {{ $contrib['type'] === 'fixed' ? number_format($contrib['rate'], 2) : $contrib['rate'] . '%' }}
                                                @if(isset($contrib['cap']) && $contrib['cap']) (cap: {{ number_format($contrib['cap'], 2) }}) @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
