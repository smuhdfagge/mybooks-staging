{{--
    Coloured status label for documents: <x-status-badge :status="$quotation->status" />
--}}
@props(['status', 'label' => null])

@php
    $colours = [
        'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'sent' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'open' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'dispatched' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'in_transit' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
        'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        'closed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
        'converted' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300',
        'expired' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300',
        'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        'active' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        'void' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        'reversed' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full '.($colours[$status] ?? $colours['draft'])]) }}>
    {{ $label ?? ucwords(str_replace('_', ' ', $status)) }}
</span>
