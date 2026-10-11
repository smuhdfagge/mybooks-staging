{{--
    Account lines inside a statement table (tables plan T6). Each account
    links to its general ledger. Accounts at zero are left out.
    @include('reports.partials.account-lines', ['lines' => $accounts, 'from' => $from, 'to' => $to, 'field' => 'balance', 'cols' => 1])
--}}
@php $field = $field ?? 'balance'; @endphp
@foreach ($lines as $account)
    @continue(abs(round((float) $account->{$field}, 2)) < 0.005)
    <tr>
        <td class="rpt-wrap rpt-in2">
            <span class="tbl-muted tabular-nums">{{ $account->account_code }}</span>
            <a href="{{ route('reports.general-ledger', ['account_id' => $account->id, 'start_date' => $from, 'end_date' => $to]) }}" class="tbl-link font-normal">{{ $account->name }}</a>
        </td>
        <td class="num">@fig($account->{$field})</td>
    </tr>
@endforeach
