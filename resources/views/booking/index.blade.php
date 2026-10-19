@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Status')
@php($me = auth()->user())
@section('title', 'Online booking & QR · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Online booking & QR</h1><p>Patients book a free slot themselves from your link, or by scanning the QR card at your desk.</p></div>
  <div class="row"><button type="button" class="btn" data-copy="{{ $link }}" data-link-copy>Copy link</button><a class="btn" href="{{ $link }}" target="_blank" rel="noopener" data-link-open>Open booking page</a></div>
</div>
<div class="grid g3b" style="margin-bottom:16px">
  <form method="post" action="{{ route('booking.save') }}" class="card stack" @if ($canManage) data-autosave @endif data-booking-form>
    @csrf
    <h2 style="margin:0">Booking settings</h2>
    @unless ($canManage)<p class="muted" style="margin:0">Only the admin or the owner doctor can change these.</p>@endunless
    <fieldset @disabled(! $canManage) class="stack" style="border:0;padding:0;margin:0">
      <label class="swrow"><span><b>Accept online bookings</b><br><small class="muted">When off, the page tells patients to call the clinic.</small></span><input type="hidden" name="booking_on" value="0"><span class="sw"><input type="checkbox" name="booking_on" value="1" @checked($c->booking_on)><span class="toggle" aria-hidden="true"></span></span></label>
      <label class="swrow"><span><b>Reception confirms each booking</b><br><small class="muted">Bookings show as "Requested online" until someone changes them to Booked.</small></span><input type="hidden" name="booking_approval" value="0"><span class="sw"><input type="checkbox" name="booking_approval" value="1" @checked($c->booking_approval)><span class="toggle" aria-hidden="true"></span></span></label>
      <div class="field"><label for="bd">Patients can book up to</label><select id="bd" name="booking_days" class="inp">@foreach ([3, 7, 14, 21, 30, 60, 90] as $n)<option value="{{ $n }}" @selected($c->booking_days === $n)>{{ $n }} days ahead</option>@endforeach</select></div>
      <div class="field"><label for="bs">Link name</label><input id="bs" name="booking_slug" class="inp mono" maxlength="40" value="{{ $c->booking_slug }}"><span class="hint">Small letters, numbers and dashes. Your link: <span class="mono">{{ url('/book') }}/<b data-slug-echo>{{ $c->booking_slug }}</b></span></span></div>
    </fieldset>
    <p class="hint" style="margin:0">Times follow your clinic hours in Settings, every {{ $c->slot_minutes }} minutes. A time is open while at least one chair is free. Patients never see who else is booked.</p>
  </form>

  <div class="card" style="text-align:center">
    <h2>Desk QR card</h2>
    <div class="qrcard" id="qrcard">
      @if ($c->logo)<img src="{{ $c->logo }}" alt="" class="qr-logo">@endif
      <b class="qr-clinic">{{ $c->name }}</b>
      <span class="qr-big">Book your next visit</span>
      <div class="qr" data-qr="{{ $link }}" role="img" aria-label="QR code for the booking page"></div>
      <span class="qr-small">Scan with your phone camera</span>
      <span class="qr-link mono" data-link-text>{{ $link }}</span>
      <span class="qr-small">{{ $c->phone }}</span>
    </div>
    <p class="hint">Print it for reception, or share the picture on WhatsApp, Instagram or your Google profile.</p>
    <div class="row" style="justify-content:center"><button type="button" class="btn pri" data-print="#qrcard" data-paper="A5" data-isolate>Print QR card</button><button type="button" class="btn" data-qr-download="{{ \Illuminate\Support\Str::slug($c->name) }}-booking-qr.png">Download picture</button></div>
  </div>

  <div class="card" id="openslots">
    <h2>Open slots</h2>
    <div class="form" style="grid-template-columns:1fr 1fr">
      <div class="field"><span class="lbl">Day</span><div data-datepicker data-plain data-name="obdate" data-value="{{ $today }}" data-min="{{ $today }}"></div></div>
      <div class="field"><label for="obch">Chair</label><select id="obch" class="inp">@foreach ($chairs as $ch)<option value="{{ $ch->id }}">{{ $ch->name }}</option>@endforeach</select></div>
    </div>
    <div data-slots style="margin-top:10px"></div>
  </div>
</div>

<div class="card">
  <h2>Upcoming online bookings</h2>
  @if ($online->isNotEmpty())
    <div class="tw"><table><thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th></tr></thead><tbody>
      @foreach ($online as $a)
        <tr class="{{ $a->status === 'CANCELLED' ? 'off' : '' }}">
          <td style="white-space:nowrap">{{ Fmt::date($a->date) }}</td><td class="mono">{{ Fmt::time($a->start_min) }}</td>
          <td>@if ($me->canOpen('patients'))<a href="{{ route('patients.show', $a->patient_id) }}">{{ $a->patient?->name }}</a>@else{{ $a->patient?->name }}@endif<br><small class="muted mono">{{ $a->patient?->code }} · {{ Fmt::showMobile($a->patient?->mobile ?? '') }}</small></td>
          <td>{{ $a->treatment }}<br><small class="muted">{{ $a->chair?->name }}</small></td>
          <td>@if ($me->canOpen('appointments'))<select class="inp" style="width:auto;padding:4px 8px" data-status="{{ route('appointments.status', $a) }}" aria-label="Status for {{ $a->patient?->name }}">@foreach (Status::STATUS as $k => $s)<option value="{{ $k }}" @selected($a->status === $k)>{{ $s['label'] }}</option>@endforeach</select>@else<span class="chip {{ Status::cls($a->status) }}">{{ Status::label($a->status) }}</span>@endif</td>
        </tr>
      @endforeach
    </tbody></table></div>
  @else
    <p class="muted">No online bookings yet. Share your link or put the QR card at reception.</p>
  @endif
</div>
@endsection
@push('vendor')<script src="{{ asset('js/vendor/qrcode.js') }}"></script>@endpush
