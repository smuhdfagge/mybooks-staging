@php
    $badge = [
        'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        'open' => 'bg-brand-100 text-brand-800 dark:bg-brand-900/50 dark:text-brand-200',
        'closed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
        'void' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
    ][$status] ?? 'bg-gray-100 text-gray-800';
    $label = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Used up', 'void' => 'Void'][$status] ?? ucfirst($status);
@endphp
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ $label }}</span>
