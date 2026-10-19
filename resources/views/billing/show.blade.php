@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Money')
@use('App\Models\Invoice')
@php($me = auth()->user())
@php([$label, $cls] = Invoice::STATE[$inv->state()])
@php($hasGst = $inv->cgst + $inv->sgst > 0)
@section('title', $inv->number.' · SmileDesk')
@section('content')
<div class="ph noprint">
  <div><h1>Bill {{ $inv->number }} <span class="chip {{ $cls }}" style="vertical-align:middle">{{ $label }}</span></h1>
    <p>Made by {{ $inv->creator?->name }} on {{ $inv->created_at->format('j M Y, g:i A') }}@if ($inv->cancelled_at) · Cancelled {{ $inv->cancelled_at->format('j M Y') }}: {{ $inv->cancel_reason }}@endif</p></div>
  <div class="row">
    <a class="btn" href="{{ route('billing.index') }}">All bills</a>
    <a class="btn" href="{{ route('billing.create', ['patient' => $inv->patient_id]) }}">New bill for {{ explode(' ', $inv->patient->name)[0] }}</a>
    <button type="button" class="btn pri" data-print=".paper">Print</button>
  </div>
</div>

<div class="billgrid">
  <div class="paper">
    <div class="lh">
      <div>@if ($c->logo)<img class="clogo" src="{{ $c->logo }}" alt="">@endif<h3>{{ $c->name }}</h3><div class="sm">{{ $c->address }}, {{ $c->city }}<br>Phone {{ $c->phone }}@if ($c->gstin)<br>GSTIN <span class="mono">{{ $c->gstin }}</span>@if (Money::stateOf($c->gstin)) · State: {{ Money::stateOf($c->gstin) }} ({{ substr($c->gstin, 0, 2) }})@endif @endif</div></div>
      <div style="text-align:right"><b style="font-size:1.05rem">{{ $inv->title($c->gstin) }}</b><div class="sm">No. <span class="mono">{{ $inv->number }}</span><br>Date {{ date('j M Y', strtotime($inv->date)) }}</div>@if ($inv->cancelled_at)<div class="stamp">CANCELLED</div>@endif</div>
    </div>
    <div class="sm" style="margin-bottom:10px">Bill to: <b style="color:#1f2422">{{ $inv->patient->name }}</b> ({{ $inv->patient->code }}) · {{ Fmt::showMobile($inv->patient->mobile) }}@if ($inv->patient->relativeLabel()) · {{ $inv->patient->relativeLabel() }}@endif @if ($c->gstin && Money::stateOf($c->gstin))<br>Place of supply: {{ Money::stateOf($c->gstin) }}@endif</div>
    <div style="overflow-x:auto"><table>
      <thead><tr><th>#</th><th>Treatment</th><th style="text-align:right">Qty × Rate</th><th style="text-align:right">Discount</th>@if ($hasGst)<th style="text-align:right">CGST</th><th style="text-align:right">SGST</th>@endif<th style="text-align:right">Amount</th></tr></thead>
      <tbody>
        @foreach ($inv->items as $n => $it)
          <tr>
            <td>{{ $n + 1 }}</td>
            <td style="min-width:150px">{{ $it->name }}@if ($it->tooth) <span class="sm">· Tooth {{ $it->tooth }}</span>@endif<br><span class="sm">SAC {{ $it->sac }}@if ($c->gstin && ! $it->gst_rate) · GST exempt @endif @if ($it->gst_rate) · Taxable {{ Money::inr($it->taxable) }} @endif</span></td>
            <td class="mono" style="text-align:right">{{ $it->qty > 1 ? $it->qty.' × ' : '' }}{{ Money::inr($it->rate) }}</td>
            <td class="mono" style="text-align:right">{{ $it->discount ? '− '.Money::inr($it->discount) : '—' }}</td>
            @if ($hasGst)
              <td class="mono" style="text-align:right">{!! $it->gst_rate ? e(Money::inr($it->cgst)).'<br><span class="sm">'.($it->gst_rate / 2).'%</span>' : '—' !!}</td>
              <td class="mono" style="text-align:right">{!! $it->gst_rate ? e(Money::inr($it->sgst)).'<br><span class="sm">'.($it->gst_rate / 2).'%</span>' : '—' !!}</td>
            @endif
            <td class="mono" style="text-align:right">{{ Money::inr($it->amount) }}</td>
          </tr>
        @endforeach
        @php($span = $hasGst ? 6 : 4)
        @if ($inv->discount)<tr><td colspan="{{ $span }}" style="text-align:right">Total discount</td><td class="mono" style="text-align:right">− {{ Money::inr($inv->discount) }}</td></tr>@endif
        @if ($hasGst)<tr><td colspan="{{ $span }}" style="text-align:right">CGST {{ Money::inr($inv->cgst) }} + SGST {{ Money::inr($inv->sgst) }}</td><td class="mono" style="text-align:right">{{ Money::inr($inv->cgst + $inv->sgst) }}</td></tr>@endif
        <tr><td colspan="{{ $span }}" style="text-align:right"><b>Total</b></td><td class="mono" style="text-align:right"><b>{{ Money::inr($inv->total) }}</b></td></tr>
        @foreach ($inv->payments as $p)
          <tr><td colspan="{{ $span }}" style="text-align:right" class="sm">Paid {{ date('j M', strtotime($p->date)) }} by {{ Money::MODES[$p->mode] ?? $p->mode }}{{ $p->reference ? ' · '.$p->reference : '' }}</td><td class="mono" style="text-align:right">− {{ Money::inr($p->amount) }}</td></tr>
        @endforeach
        @unless ($inv->cancelled_at)<tr><td colspan="{{ $span }}" style="text-align:right"><b>Balance due</b></td><td class="mono" style="text-align:right"><b>{{ Money::inr($inv->due()) }}</b></td></tr>@endunless
      </tbody>
    </table></div>
    <p class="sm" style="margin-top:10px">{{ Money::words($inv->total) }}</p>
    @if ($inv->notes)<p class="sm">Note: {{ $inv->notes }}</p>@endif
    @if ($c->gstin)<p class="sm">Healthcare services by a clinical establishment are exempt from GST.{{ $hasGst ? ' Cosmetic procedures are taxed (CGST + SGST).' : '' }}</p>@endif
    <div class="sign"><div>For {{ $c->name }}</div></div>
  </div>

  <div class="stack noprint">
    @if (! $inv->cancelled_at && $inv->due() > 0)
      <form method="post" action="{{ route('billing.pay', $inv) }}" class="card">
        @csrf
        <h2>Record payment</h2>
        <div class="stack">
          <div class="field"><label for="amount">Amount ₹</label><input id="amount" name="amount" class="inp mono" inputmode="decimal" value="{{ old('amount', Money::plain($inv->due())) }}"><span class="hint">Balance due {{ Money::inr($inv->due()) }}</span></div>
          <div class="field"><label for="mode">Paid by</label><select id="mode" name="mode" class="inp">@foreach (Money::MODES as $k => $l)<option value="{{ $k }}" @selected(old('mode', 'CASH') === $k)>{{ $l }}</option>@endforeach</select></div>
          <div class="field"><label for="reference">Reference</label><input id="reference" name="reference" class="inp" placeholder="UPI ref, card last 4 digits, cheque no." value="{{ old('reference') }}"></div>
          <button class="btn pri">Save payment</button>
        </div>
      </form>
    @endif
    <div class="card">
      <h2>Payments</h2>
      @forelse ($inv->payments as $p)
        <div class="row" style="justify-content:space-between;padding:6px 0;border-top:1px solid var(--line)">
          <div><b class="mono">{{ Money::inr($p->amount) }}</b> · {{ Money::MODES[$p->mode] ?? $p->mode }}<br><small class="muted">{{ date('j M Y', strtotime($p->date)) }} · {{ $p->receiver?->name }}{{ $p->reference ? ' · '.$p->reference : '' }}</small></div>
          @if ($me->isAdmin())<form method="post" action="{{ route('payments.destroy', $p) }}" data-confirm="Remove this payment of {{ Money::inr($p->amount) }}? This cannot be undone.">@csrf @method('DELETE')<button class="btn sm danger">Remove</button></form>@endif
        </div>
      @empty
        <p class="muted" style="margin:0">No payment yet.</p>
      @endforelse
    </div>
    @unless ($inv->cancelled_at)
      <form method="post" action="{{ route('billing.cancel', $inv) }}" class="card" data-confirm="Cancel bill {{ $inv->number }}? The number stays used and the bill is kept as cancelled.">
        @csrf
        <h2>Cancel this bill</h2>
        <div class="stack"><input name="reason" class="inp" placeholder="Reason, e.g. wrong treatment entered" aria-label="Reason for cancelling" required minlength="3"><button class="btn danger">Cancel bill</button></div>
        @if ($inv->paid > 0)<p class="hint" style="margin-bottom:0">This bill has payments. The admin must remove them first.</p>@endif
      </form>
    @endunless
    @if ($me->isAdmin())
      <form method="post" action="{{ route('billing.destroy', $inv) }}" data-confirm="Delete bill {{ $inv->number }} and its payments permanently? This cannot be undone.">@csrf @method('DELETE')<button class="btn danger">Delete permanently</button></form>
    @endif
  </div>
</div>
@endsection
