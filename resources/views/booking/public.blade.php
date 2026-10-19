@extends('layouts.base')
@use('App\Support\Fmt')
@section('title', 'Book an appointment · '.$c->name)
@section('bodyclass', 'pub-body')
@section('body')
<div class="pub">
  <header class="pub-top">
    @if ($c->logo)<img src="{{ $c->logo }}" alt="" class="pub-logo">@endif
    <div><h1>{{ $c->name }}</h1><p>{{ $c->address }}, {{ $c->city }}<br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->phone) }}">{{ $c->phone }}</a>@if ($doctor) · {{ $doctor->name }}@endif</p></div>
  </header>
  @if ($b = session('booked'))
    <section class="pub-card pub-done" role="status">
      <div class="pub-tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
      <h2>{{ $b['pending'] ? 'Request sent' : 'You are booked' }}</h2>
      <p><b>{{ $b['name'] }}</b>, {{ $b['when'] }}</p>
      <p class="muted">{{ $b['pending'] ? 'The clinic will call you to confirm this time.' : 'Please come 5 minutes early. To change the time, call the clinic.' }}</p>
      <a class="btn" href="{{ route('book.page', $c->booking_slug) }}">Book another appointment</a>
    </section>
  @elseif (! $c->booking_on)
    <section class="pub-card"><h2>Online booking is closed</h2><p class="muted">Please call the clinic at <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->phone) }}">{{ $c->phone }}</a> to book.</p></section>
  @else
    <form class="pub-card stack" method="post" action="{{ route('book.store', $c->booking_slug) }}" id="pubbook" data-slots-url="{{ route('book.slots', $c->booking_slug) }}" data-old-date="{{ old('date') }}" data-old-time="{{ old('time') }}">
      @csrf
      @include('partials.flash')
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <input type="hidden" name="date" data-date-value>
      <input type="hidden" name="time" data-time-value>
      <div><h2>1. Pick a day</h2>
        <div class="pub-days" role="radiogroup" aria-label="Day">
          @foreach ($days as $i => $d)
            <button type="button" class="pub-day" data-day="{{ $d }}" role="radio" aria-checked="false"><small>{{ $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : date('D', strtotime($d))) }}</small><b>{{ date('j', strtotime($d)) }}</b><small>{{ date('M', strtotime($d)) }}</small></button>
          @endforeach
        </div>
      </div>
      <div><h2>2. Pick a time</h2><div class="pub-times" data-times><p class="muted">Pick a day first.</p></div></div>
      <div class="stack"><h2>3. Your details</h2>
        <div class="field"><label for="bn">Full name</label><input id="bn" name="name" class="inp" required maxlength="100" autocomplete="name" value="{{ old('name') }}"></div>
        <div class="field"><label for="bm">Mobile number</label><input id="bm" name="mobile" class="inp" required inputmode="tel" autocomplete="tel" value="{{ old('mobile') }}" placeholder="10-digit mobile"></div>
        <div class="field"><label for="br">Reason (optional)</label><input id="br" name="reason" class="inp" maxlength="100" value="{{ old('reason') }}" placeholder="e.g. Tooth pain, cleaning, braces check"></div>
      </div>
      <button class="btn pri pub-btn" data-book-btn disabled>Pick a day and time</button>
      <p class="hint" style="text-align:center;margin:0">Your details go only to {{ $c->name }}.</p>
    </form>
  @endif
  <p class="pub-foot">Booking by SmileDesk</p>
</div>
@endsection
