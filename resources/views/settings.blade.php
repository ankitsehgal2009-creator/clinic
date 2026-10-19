@extends('layouts.app')
@php($ro = ! auth()->user()->canManageClinic())
@php($hhmm = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60))
@php($times = array_map($hhmm, range(0, 24 * 60 - 15, 15)))
@section('title', 'Settings · SmileDesk')
@section('content')
<div class="ph"><div><h1>Settings</h1><p>Clinic details and working hours. Appointment slots follow these hours every 15 minutes.@unless ($ro) Changes save by themselves.@endunless</p></div></div>
@if ($ro)<p class="muted">Only the admin or the owner doctor can change these.</p>@endif
<div class="grid" style="grid-template-columns:minmax(0,1fr);gap:16px">
  <section class="card">
    <h2>Clinic logo</h2>
    <div class="logo-up" data-logo data-logo-save="{{ $ro ? '' : route('settings.logo') }}">
      <button type="button" class="logo-box" data-logo-pick aria-label="Upload clinic logo" @disabled($ro)>@if ($c->logo)<img src="{{ $c->logo }}" alt="Clinic logo">@else<span>+ Logo</span>@endif</button>
      <div class="hint">PNG or JPG, ideally square or wide. It is shown in the menu and printed on bills and the cash report.
        @unless ($ro)<br><button type="button" class="linkbtn {{ $c->logo ? '' : 'hide' }}" data-logo-clear>Remove logo</button>@endunless</div>
    </div>
  </section>
  <form method="post" action="{{ route('settings') }}" class="card" @unless ($ro) data-autosave @endunless>
    @csrf
    <h2>Clinic details and hours</h2>
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
      <h2 style="margin-top:22px">Prescription design</h2>
      <p class="hint" style="margin-top:-6px">Used for every new prescription. The doctor can still pick another design for one print.</p>
      <div class="rxd" role="radiogroup" aria-label="Prescription design">
        @foreach (\App\Support\Rx::DESIGNS as $k => [$n, $why])
          <label class="rxd-card">
            <input type="radio" name="rx_design" value="{{ $k }}" @checked($c->rx_design === $k)>
            <div class="thumb">@include('rx.paper', ['d' => $k, 'doc' => $doc, 'pt' => $sample['pt'], 'v' => $sample['v'], 'meds' => $sample['meds']])</div>
            <b>{{ $n }}</b><small class="muted">{{ $why }}</small>
          </label>
        @endforeach
      </div>
      @unless ($ro)<noscript><div style="margin-top:16px"><button class="btn pri">Save</button></div></noscript>@endunless
    </fieldset>
  </form>
</div>
@endsection
