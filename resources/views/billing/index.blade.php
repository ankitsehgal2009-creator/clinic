@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Money')
@use('App\Models\Invoice')
@section('title', 'Billing & GST · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Billing &amp; GST</h1><p>Dental treatment is GST-exempt. Cosmetic work such as whitening is charged 18%, split into CGST and SGST.</p></div>
  <div class="row"><a class="btn" href="{{ route('billing.prices') }}">Price list</a><a class="btn pri" href="{{ route('billing.create') }}">New bill</a></div>
</div>
<div class="grid g4" style="margin-bottom:16px">
  <div class="card kpi"><span class="l">Billed today</span><span class="v">{{ Money::inr($billedToday) }}</span></div>
  <div class="card kpi"><span class="l">Collected today</span><span class="v">{{ Money::inr($collectedToday) }}</span></div>
  <div class="card kpi"><span class="l">Billed this month</span><span class="v">{{ Money::inr($billedMonth) }}</span></div>
  <div class="card kpi"><span class="l">Unpaid balance</span><span class="v">{{ Money::inr($outstanding) }}</span><span class="muted">All patients</span></div>
</div>
<div class="card">
  <form class="row" method="get" style="margin-bottom:14px;justify-content:space-between">
    <div class="pills">@foreach (\App\Http\Controllers\BillingController::SHOW as $k => $l)<a class="{{ $show === $k ? 'on' : '' }}" href="?show={{ $k }}{{ $term !== '' ? '&q='.urlencode($term) : '' }}">{{ $l }}</a>@endforeach</div>
    <div class="row"><input type="hidden" name="show" value="{{ $show }}"><input class="inp" style="width:260px" name="q" value="{{ $term }}" placeholder="Patient, ID, mobile or bill number" aria-label="Search bills"><button class="btn">Search</button></div>
  </form>
  @if ($rows->isNotEmpty())
    <div class="tw"><table><thead><tr><th>Bill</th><th>Date</th><th>Patient</th><th style="text-align:right">Total</th><th style="text-align:right">Balance</th><th>Status</th></tr></thead><tbody>
      @foreach ($rows as $i)
        @php([$label, $cls] = Invoice::STATE[$i->state()])
        <tr class="{{ $i->cancelled_at ? 'off' : '' }}">
          <td><a class="mono" href="{{ route('billing.show', $i) }}">{{ $i->number }}</a></td>
          <td>{{ Fmt::date($i->date) }}</td>
          <td><a href="{{ route('patients.show', $i->patient_id) }}">{{ $i->patient->name }}</a> <small class="mono muted">{{ $i->patient->code }}</small></td>
          <td class="mono" style="text-align:right">{{ Money::inr($i->total) }}</td>
          <td class="mono" style="text-align:right">{{ $i->due() ? Money::inr($i->due()) : '—' }}</td>
          <td><span class="chip {{ $cls }}">{{ $label }}</span></td>
        </tr>
      @endforeach
    </tbody></table></div>
  @else
    <p class="muted">{{ $term !== '' ? 'No bills match "'.$term.'".' : 'No bills here yet.' }} <a href="{{ route('billing.create') }}">Make a bill</a></p>
  @endif
</div>
@endsection
