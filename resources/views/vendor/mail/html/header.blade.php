@props(['url'])
{{-- MyBooks logo at the top of every email (rebrand R10). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === config('app.name') || trim($slot) === 'Laravel')
<img src="{{ asset('images/brand/mybooks-logo-email-300.png') }}" class="logo" width="150" alt="{{ config('app.name') }}" style="height: auto; max-height: 40px; width: auto;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
