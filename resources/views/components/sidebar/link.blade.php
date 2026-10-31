{{-- A page in a sidebar group (audit MB-50): one look for every link. --}}
@props(['href', 'active' => false])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   {{ $attributes->class(['flex min-h-9 items-center px-3 py-2 text-sm rounded-lg', $active ? 'nav-active' : 'text-gray-300 hover:text-white hover:bg-brand-800']) }}>{{ $slot }}</a>
