@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Money')
@section('title', 'Statement · '.$p->name.' · SmileDesk')
@section('content')
<div class="ph noprint">
  <div><h1>Statement</h1><p>Every bill and payment for {{ $p->name }}, oldest first, with the balance after each line.</p></div>
  <div class="row">
    <a class="btn" href="{{ route('patients.show', $p) }}">Patient file</a>
    <a class="btn" href="{{ route('billing.create', ['patient' => $p->id]) }}">New bill</a>
    <button type="button" class="btn pri" data-print=".paper">Print</button>
  </div>
</div>
<div class="grid g4 noprint" style="margin-bottom:16px">
  <div class="card stat"><small class="muted">Treatment charges</small><b class="mono">{{ Money::inr($charged) }}</b></div>
  <div class="card stat"><small class="muted">Paid</small><b class="mono" style="color:var(--ok)">{{ Money::inr($paid) }}</b></div>
  <div class="card stat"><small class="muted">Balance due</small><b class="mono" style="color:{{ $balance > 0 ? 'var(--bad)' : 'var(--ink)' }}" data-balance>{{ Money::inr($balance) }}</b></div>
</div>
<div class="paper stmt">
  <div class="lh">
    <div>@if ($c->logo)<img class="clogo" src="{{ $c->logo }}" alt="">@endif<h3>{{ $c->name }}</h3><div class="sm">{{ $c->address }}, {{ $c->city }}<br>Phone {{ $c->phone }}</div></div>
    <div style="text-align:right"><b style="font-size:1.05rem">Patient statement</b><div class="sm">{{ date('j M Y') }}</div></div>
  </div>
  <div class="sm" style="margin-bottom:10px"><b class="ink">{{ $p->name }}</b> ({{ $p->code }}) · {{ Fmt::showMobile($p->mobile) }}@if ($p->relativeLabel()) · {{ $p->relativeLabel() }}@endif</div>
  @if ($rows)
  <div style="overflow-x:auto"><table class="stmt-t">
    <thead><tr><th>Date</th><th>Details</th><th style="text-align:right">Charges</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th></tr></thead>
    <tbody>
    @foreach ($rows as $r)
      @if ($r['kind'] === 'bill')
        <tr class="{{ $r['bill']->cancelled_at ? 'off' : '' }}">
          <td class="nw">{{ date('j M Y', strtotime($r['date'])) }}</td>
          <td>Bill <a class="mono" href="{{ route('billing.show', $r['bill']) }}">{{ $r['bill']->number }}</a>@if ($r['bill']->cancelled_at) <span class="chip">Cancelled</span>@endif<br><small class="sm">{{ $r['what'] ?: 'Treatment' }}</small></td>
          <td class="mono r">{{ $r['bill']->cancelled_at ? '—' : Money::inr($r['charge']) }}</td><td></td>
          <td class="mono r">{{ Money::inr($r['balance']) }}</td>
        </tr>
      @else
        <tr class="{{ $r['paid'] ? '' : 'off' }}">
          <td class="nw">{{ date('j M Y', strtotime($r['date'])) }}</td>
          <td>Payment · {{ Money::MODES[$r['pay']->mode] ?? $r['pay']->mode }}@if ($r['pay']->reference) · {{ $r['pay']->reference }}@endif<br><small class="sm">For bill <span class="mono">{{ $r['bill']?->number }}</span>@if ($r['pay']->receiver) · received by {{ $r['pay']->receiver->name }}@endif</small></td>
          <td></td><td class="mono r">{{ Money::inr($r['pay']->amount) }}</td>
          <td class="mono r">{{ Money::inr($r['balance']) }}</td>
        </tr>
      @endif
    @endforeach
    </tbody>
    <tfoot><tr><td colspan="2"><b>Total</b></td><td class="mono r"><b>{{ Money::inr($charged) }}</b></td><td class="mono r"><b>{{ Money::inr($paid) }}</b></td><td class="mono r"><b>{{ Money::inr($balance) }}</b></td></tr></tfoot>
  </table></div>
  @else
    <p class="sm">No bills yet.</p>
  @endif
  <div class="print-credit">Designed &amp; Developed By Er. Ankit Sehgal</div>
</div>
@endsection
