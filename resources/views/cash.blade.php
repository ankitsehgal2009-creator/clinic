@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Money')
@php($me = auth()->user())
@php($isToday = $date === Fmt::today())
@section('title', 'Daily cash closing · SmileDesk')
@section('bodyclass', 'has-report')
@section('content')
<div class="ph">
  <div><h1>Daily cash closing</h1><p>{{ Fmt::date($date) }}@if ($closing) · closed by {{ $closing->closer?->name }} at {{ $closing->created_at->format('g:i A') }}@endif</p></div>
  <div class="row"><div data-datepicker data-plain data-value="{{ $date }}" data-max="{{ Fmt::today() }}" data-go="{{ route('cash.show') }}"></div>
    @if ($closing)<span class="chip c-ok">Day closed</span>@endif
    <button type="button" class="btn" data-print=".print-report">Print</button></div>
</div>
<div class="grid g4" style="margin-bottom:16px">
  @foreach (['CASH', 'UPI', 'CARD'] as $m)
    <div class="card kpi"><span class="l">{{ $byMode[$m]['label'] }}</span><span class="v">{{ Money::inr($byMode[$m]['sum']) }}</span><span class="muted">{{ $byMode[$m]['n'] }} {{ $byMode[$m]['n'] === 1 ? 'payment' : 'payments' }}</span></div>
  @endforeach
  <div class="card kpi"><span class="l">Total collected</span><span class="v">{{ Money::inr($collected) }}</span>@if ($byMode['BANK']['sum'] + $byMode['CHEQUE']['sum'])<span class="muted">Includes {{ Money::inr($byMode['BANK']['sum'] + $byMode['CHEQUE']['sum']) }} bank and cheque</span>@endif</div>
</div>

<div class="grid g2" style="margin-bottom:16px">
  <div class="card">
    <h2>Cash in drawer</h2>
    @php($c = $closing)
    <table class="totals" style="width:100%">
      <tr><td>Opening balance<br><small class="muted">{{ $prev ? 'Left in the drawer on '.date('j M', strtotime($prev->date)) : 'No earlier closing' }}</small></td><td class="mono">{{ Money::inr($c->opening ?? $opening) }}</td></tr>
      <tr><td>Cash received</td><td class="mono">+ {{ Money::inr($c->cash_in ?? $cashIn) }}</td></tr>
      <tr><td>Paid out</td><td class="mono">− {{ Money::inr($c->paid_out ?? $paidOut) }}</td></tr>
      <tr><td><b>Should be in drawer</b></td><td class="mono"><b>{{ Money::inr($c->expected ?? $expected) }}</b></td></tr>
      @if ($c)
        <tr><td>Counted</td><td class="mono">{{ Money::inr($c->counted) }}</td></tr>
        <tr><td>Difference</td><td>@if ($c->counted === $c->expected)<span class="chip c-ok">Matched</span>@else<span class="chip c-bad">{{ $c->counted > $c->expected ? 'Extra' : 'Short' }} {{ Money::inr(abs($c->counted - $c->expected)) }}</span>@endif</td></tr>
        @if ($c->reason)<tr><td colspan="2"><span class="muted">Reason:</span> {{ $c->reason }}</td></tr>@endif
        <tr><td>Left in drawer for tomorrow</td><td class="mono">{{ Money::inr($c->kept) }}</td></tr>
        @if ($c->counted > $c->kept)<tr><td>Taken out (bank or owner)</td><td class="mono">{{ Money::inr($c->counted - $c->kept) }}</td></tr>@endif
      @endif
    </table>
    @if ($c && $cashIn !== $c->cash_in)<p class="err" style="margin-bottom:0">Cash payments changed after closing: {{ Money::inr($cashIn) }} now, {{ Money::inr($c->cash_in) }} at closing.</p>@endif
    @if ($c && $me->isAdmin())
      <form method="post" action="{{ route('cash.reopen') }}" class="noprint" style="margin-top:12px" data-confirm="Reopen {{ Fmt::date($date) }}? The closing will be removed and can be done again.">@csrf<input type="hidden" name="date" value="{{ $date }}"><button class="btn sm">Reopen this day</button></form>
    @endif
  </div>

  @if ($closing)
    <div class="card">
      <h2>Notes counted</h2>
      <table class="totals" style="width:100%">
        @foreach (Money::NOTES as $n)@if ($closing->notes_count[$n] ?? 0)<tr><td class="mono">₹{{ $n }} × {{ $closing->notes_count[$n] }}</td><td class="mono">{{ Money::inr($n * $closing->notes_count[$n] * 100) }}</td></tr>@endif @endforeach
        @if ($closing->notes_count['coins'] ?? 0)<tr><td>Coins</td><td class="mono">{{ Money::inr($closing->notes_count['coins'] * 100) }}</td></tr>@endif
        <tr><td><b>Total</b></td><td class="mono"><b>{{ Money::inr($closing->counted) }}</b></td></tr>
      </table>
    </div>
  @else
    <form method="post" action="{{ route('cash.close') }}" class="card" data-cash data-expected="{{ $expected }}">
      @csrf
      <input type="hidden" name="date" value="{{ $date }}">
      <h2>Count the notes</h2>
      <div class="denom">
        @foreach (Money::NOTES as $n)
          <span class="mono">₹{{ $n }} ×</span><input class="inp mono" type="number" min="0" name="notes[{{ $n }}]" value="{{ old('notes.'.$n, 0) }}" data-note="{{ $n }}" aria-label="Number of ₹{{ $n }} notes"><span class="mono" data-sub>₹0</span>
        @endforeach
        <span>Coins ₹</span><input class="inp mono" type="number" min="0" name="coins" value="{{ old('coins', 0) }}" data-note="1" aria-label="Coins total in rupees"><span class="mono" data-sub>₹0</span>
      </div>
      <div class="row" style="justify-content:space-between;margin-top:14px;padding-top:12px;border-top:1px solid var(--line)">
        <span>Counted <b class="mono" data-counted>₹0</b></span><span class="chip" data-diff></span>
      </div>
      <div class="field hide" data-reason style="margin-top:12px"><label for="reason">Why is it different?</label><input id="reason" name="reason" class="inp" maxlength="250" value="{{ old('reason') }}" placeholder="e.g. ₹100 change given from own pocket"></div>
      <div class="field" style="margin-top:12px"><label for="kept">Leave in drawer for tomorrow ₹</label><input id="kept" name="kept" class="inp mono" inputmode="decimal" value="{{ old('kept') }}" placeholder="All of it"><span class="hint">If you take cash to the bank or give it to the owner, enter what stays in the drawer.</span></div>
      <div style="margin-top:14px"><button class="btn pri" data-close>Close the day</button></div>
    </form>
  @endif
</div>

<div class="grid g2">
  <div class="card">
    <h2>Paid out of the drawer</h2>
    @forelse ($payouts as $p)
      <div class="row" style="justify-content:space-between;padding:6px 0;border-top:1px solid var(--line)">
        <div>{{ $p->description }}<br><small class="muted">{{ $p->user?->name }} · {{ $p->created_at->format('g:i A') }}</small></div>
        <div class="row"><b class="mono">{{ Money::inr($p->amount) }}</b>
          @if ($me->isAdmin() && ! $closing)<form method="post" action="{{ route('cash.payout.destroy', $p) }}" data-confirm="Delete this payout permanently?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>@endif</div>
      </div>
    @empty
      <p class="muted">Nothing paid out{{ $isToday ? ' yet' : '' }}.</p>
    @endforelse
    @if ($isToday && ! $closing)
      <form method="post" action="{{ route('cash.payout') }}" class="row noprint" style="margin-top:12px;flex-wrap:nowrap">
        @csrf
        <input name="description" class="inp" placeholder="What for, e.g. courier to lab" aria-label="Paid out for" required>
        <input name="amount" class="inp mono" style="width:110px" inputmode="decimal" placeholder="₹" aria-label="Amount paid out" required>
        <button class="btn">Add</button>
      </form>
    @endif
  </div>
  <div class="card">
    <h2>Payments received</h2>
    @if ($payments->isNotEmpty())
      <div class="tw"><table><tbody>
        @foreach ($payments as $p)
          <tr><td>{{ $p->patient?->name }}<br><small class="muted"><a class="mono" href="{{ $me->canOpen('billing') ? route('billing.show', $p->invoice_id) : '#' }}">{{ $p->invoice?->number }}</a> · {{ $p->receiver?->name }}</small></td><td>{{ Money::MODES[$p->mode] ?? $p->mode }}</td><td class="mono" style="text-align:right">{{ Money::inr($p->amount) }}</td></tr>
        @endforeach
      </tbody></table></div>
    @else
      <p class="muted">No payments on this day.</p>
    @endif
  </div>
</div>
{{-- One-page report used only for printing; the Print button scales it to fit the chosen paper --}}
@php($cl = $me->clinic)
@php($c = $closing)
<div class="print-report rep">
  <div class="lh">
    <div>@if ($cl->logo)<img src="{{ $cl->logo }}" alt="">@endif<h3>{{ $cl->name }}</h3><div>{{ $cl->address }}, {{ $cl->city }} · {{ $cl->phone }}</div></div>
    <div style="text-align:right"><b style="font-size:14px">Daily cash closing</b><div>{{ Fmt::date($date) }}</div><div>{{ $c ? 'Closed by '.$c->closer?->name.' at '.$c->created_at->format('g:i A') : 'Not closed yet' }}</div></div>
  </div>
  <div class="kp">
    @foreach (['CASH', 'UPI', 'CARD'] as $m)<div>{{ $byMode[$m]['label'] }}<b>{{ Money::inr($byMode[$m]['sum']) }}</b>{{ $byMode[$m]['n'] }} {{ $byMode[$m]['n'] === 1 ? 'payment' : 'payments' }}</div>@endforeach
    <div>Total collected<b>{{ Money::inr($collected) }}</b>@if ($byMode['BANK']['sum'] + $byMode['CHEQUE']['sum'])incl. {{ Money::inr($byMode['BANK']['sum'] + $byMode['CHEQUE']['sum']) }} bank/cheque @endif</div>
  </div>
  <div class="cols">
    <div>
      <h4>Cash in drawer</h4>
      <table>
        <tr><td>Opening balance</td><td class="r">{{ Money::inr($c->opening ?? $opening) }}</td></tr>
        <tr><td>Cash received</td><td class="r">+ {{ Money::inr($c->cash_in ?? $cashIn) }}</td></tr>
        <tr><td>Paid out</td><td class="r">− {{ Money::inr($c->paid_out ?? $paidOut) }}</td></tr>
        <tr><td><b>Should be in drawer</b></td><td class="r"><b>{{ Money::inr($c->expected ?? $expected) }}</b></td></tr>
        @if ($c)
          <tr><td>Counted</td><td class="r">{{ Money::inr($c->counted) }}</td></tr>
          <tr><td>Difference</td><td class="r">{{ $c->counted === $c->expected ? 'Matched' : ($c->counted > $c->expected ? 'Extra ' : 'Short ').Money::inr(abs($c->counted - $c->expected)) }}</td></tr>
          @if ($c->reason)<tr><td colspan="2">Reason: {{ $c->reason }}</td></tr>@endif
          <tr><td>Left in drawer</td><td class="r">{{ Money::inr($c->kept) }}</td></tr>
          @if ($c->counted > $c->kept)<tr><td>Taken out (bank or owner)</td><td class="r">{{ Money::inr($c->counted - $c->kept) }}</td></tr>@endif
        @endif
      </table>
    </div>
    <div>
      <h4>Notes counted</h4>
      @if ($c)
        <table>
          <tr><th>Note</th><th class="r">Count</th><th class="r">Amount</th></tr>
          @foreach (Money::NOTES as $n)<tr><td>₹{{ $n }}</td><td class="r">{{ $c->notes_count[$n] ?? 0 }}</td><td class="r">{{ Money::inr($n * ($c->notes_count[$n] ?? 0) * 100) }}</td></tr>@endforeach
          <tr><td>Coins</td><td class="r"></td><td class="r">{{ Money::inr(($c->notes_count['coins'] ?? 0) * 100) }}</td></tr>
          <tr><td><b>Total</b></td><td></td><td class="r"><b>{{ Money::inr($c->counted) }}</b></td></tr>
        </table>
      @else
        <p>The day is not closed yet, so no notes were counted.</p>
      @endif
    </div>
  </div>
  <h4>Paid out of the drawer</h4>
  @if ($payouts->isNotEmpty())
    <table><tr><th>What for</th><th>By</th><th>Time</th><th class="r">Amount</th></tr>
      @foreach ($payouts as $p)<tr><td>{{ $p->description }}</td><td>{{ $p->user?->name }}</td><td>{{ $p->created_at->format('g:i A') }}</td><td class="r">{{ Money::inr($p->amount) }}</td></tr>@endforeach
      <tr><td colspan="3"><b>Total paid out</b></td><td class="r"><b>{{ Money::inr($paidOut) }}</b></td></tr></table>
  @else
    <p>Nothing paid out.</p>
  @endif
  <h4>Payments received</h4>
  @if ($payments->isNotEmpty())
    <table><tr><th>Patient</th><th>Bill</th><th>Mode</th><th>Received by</th><th class="r">Amount</th></tr>
      @foreach ($payments as $p)<tr><td>{{ $p->patient?->name }} ({{ $p->patient?->code }})</td><td>{{ $p->invoice?->number }}</td><td>{{ Money::MODES[$p->mode] ?? $p->mode }}</td><td>{{ $p->receiver?->name }}</td><td class="r">{{ Money::inr($p->amount) }}</td></tr>@endforeach
      <tr><td colspan="4"><b>Total</b></td><td class="r"><b>{{ Money::inr($collected) }}</b></td></tr></table>
  @else
    <p>No payments on this day.</p>
  @endif
  <div class="signs"><div>Counted by</div><div>Checked by</div></div>
</div>
@endsection
