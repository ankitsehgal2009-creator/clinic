@extends('layouts.app')
@use('App\Support\Money')
@section('title', 'Price list · SmileDesk')
@section('content')
<div class="ph"><div><h1>Price list</h1><p>Prices fill in automatically when you add a treatment to a bill. You can still change them on the bill.</p></div><a class="btn" href="{{ route('billing.index') }}">Back to bills</a></div>
<form method="post" action="{{ route('billing.prices.store') }}" class="card" style="margin-bottom:16px">
  @csrf
  <h2>Add a treatment</h2>
  <div class="form">
    <div class="field"><label for="pn">Treatment</label><input id="pn" name="name" class="inp" required value="{{ old('name') }}"></div>
    <div class="field"><label for="pp">Price ₹</label><input id="pp" name="price" class="inp mono" inputmode="decimal" required value="{{ old('price') }}"></div>
    <div class="field"><label for="pg">GST</label><select id="pg" name="gst_rate" class="inp" @disabled(! $gst)>@foreach (Money::GST_RATES as $g)<option value="{{ $g }}">{{ $g ? $g.'%' : 'Exempt' }}</option>@endforeach</select>@unless ($gst)<input type="hidden" name="gst_rate" value="0">@endunless</div>
    <div class="field"><label for="ps">SAC code</label><input id="ps" name="sac" class="inp mono" placeholder="999313" value="{{ old('sac') }}"></div>
  </div>
  <div style="margin-top:14px"><button class="btn pri">Add</button></div>
</form>
<div class="tw"><table class="prices">
  <thead><tr><th>Treatment</th><th>Price ₹</th><th>GST</th><th>SAC</th><th></th><th style="text-align:center">On</th></tr></thead>
  <tbody>
  @foreach ($rows as $p)
    <tr class="{{ $p->active ? '' : 'off' }}">
      <td><form id="pr{{ $p->id }}" method="post" action="{{ route('billing.prices.update', $p) }}">@csrf</form><input form="pr{{ $p->id }}" name="name" class="inp" value="{{ $p->name }}" aria-label="Treatment"></td>
      <td><input form="pr{{ $p->id }}" name="price" class="inp mono" value="{{ Money::plain($p->price) }}" inputmode="decimal" aria-label="Price for {{ $p->name }}"></td>
      <td><select form="pr{{ $p->id }}" name="gst_rate" class="inp" aria-label="GST for {{ $p->name }}">@foreach (Money::GST_RATES as $g)<option value="{{ $g }}" @selected($p->gst_rate === $g)>{{ $g ? $g.'%' : 'Exempt' }}</option>@endforeach</select></td>
      <td><input form="pr{{ $p->id }}" name="sac" class="inp mono" value="{{ $p->sac }}" aria-label="SAC for {{ $p->name }}"></td>
      <td><button form="pr{{ $p->id }}" class="btn sm">Save</button></td>
      <td style="text-align:center"><button type="button" class="toggle {{ $p->active ? 'on' : '' }}" aria-pressed="{{ $p->active ? 'true' : 'false' }}" aria-label="Use {{ $p->name }}" data-toggle="{{ route('billing.prices.active', $p) }}"></button></td>
    </tr>
  @endforeach
  </tbody>
</table></div>
<p class="hint">Turn a treatment off to hide it from new bills. Old bills keep their prices.</p>
@endsection
