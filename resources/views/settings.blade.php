@extends('layouts.app')
@php($ro = ! auth()->user()->isAdmin())
@php($hhmm = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60))
@php($times = array_map($hhmm, range(0, 24 * 60 - 15, 15)))
@section('title', 'Settings · SmileDesk')
@section('content')
<div class="ph"><div><h1>Settings</h1><p>Clinic details and working hours. Appointment slots follow these hours every 15 minutes.</p></div></div>
<form method="post" action="{{ route('settings') }}" class="card">
  @csrf
  @if ($ro)<p class="muted">Only the admin can change these.</p>@endif
  <fieldset @disabled($ro) style="border:0;padding:0;margin:0">
    <div class="form">
      <div class="field full"><label for="name">Clinic name</label><input id="name" name="name" class="inp" value="{{ old('name', $c->name) }}"></div>
      <div class="field full"><label for="address">Address</label><input id="address" name="address" class="inp" value="{{ old('address', $c->address) }}"></div>
      <div class="field"><label for="city">City and PIN</label><input id="city" name="city" class="inp" value="{{ old('city', $c->city) }}"></div>
      <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" class="inp" value="{{ old('phone', $c->phone) }}"></div>
      <div class="field"><label for="gstin">GSTIN</label><input id="gstin" name="gstin" class="inp mono" maxlength="15" value="{{ old('gstin', $c->gstin) }}"><span class="hint">Printed on every bill. Leave blank if the clinic isn't registered for GST; bills then carry no GST.</span></div>
      <div class="field"><label for="invoice_prefix">Bill number starts with</label><input id="invoice_prefix" name="invoice_prefix" class="inp mono" maxlength="8" value="{{ old('invoice_prefix', $c->invoice_prefix) }}"><span class="hint">Bills are numbered like {{ $c->invoice_prefix }}/{{ \App\Support\Money::fy(\App\Support\Fmt::today()) }}/0001 and restart every 1 April.</span></div>
      <div class="field"><label for="chairs">Chairs</label><input id="chairs" name="chairs" type="number" min="1" max="20" class="inp" value="{{ old('chairs', $chairs) }}"></div>
      @foreach (['morning_open' => 'Opens', 'morning_close' => 'Break starts', 'evening_open' => 'Break ends', 'evening_close' => 'Closes'] as $k => $l)
        <div class="field"><label for="{{ $k }}">{{ $l }}</label><select id="{{ $k }}" name="{{ $k }}" class="inp">@foreach ($times as $t)<option value="{{ $t }}" @selected(old($k, $hhmm($c->$k)) === $t)>{{ \App\Support\Fmt::time((int) substr($t, 0, 2) * 60 + (int) substr($t, 3)) }}</option>@endforeach</select></div>
      @endforeach
    </div>
    @unless ($ro)<div style="margin-top:16px"><button class="btn pri">Save</button></div>@endunless
  </fieldset>
</form>
@endsection
