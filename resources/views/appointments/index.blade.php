@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Status')
@php($admin = auth()->user()->isAdmin())
@php($shift = fn ($n) => $view === 'month' ? date('Y-m-d', strtotime(substr($date, 0, 8).'01 '.$n.' month')) : Fmt::addDays($date, $n * ($view === 'week' ? 7 : 1)))
@section('title', 'Appointments · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Appointments</h1><p>
    @if ($view === 'day') {{ Fmt::date($date) }} · {{ $live->count() }} appointments
    @elseif ($view === 'week') Week of {{ Fmt::date($from) }} · {{ $live->count() }} appointments
    @else {{ date('F Y', strtotime($from)) }} · {{ $live->count() }} appointments @endif
  </p></div>
  <div class="row">
    <div class="row">
      <a class="btn sm" href="{{ route('appointments.index', ['date' => $shift(-1), 'view' => $view]) }}" aria-label="Previous">‹</a>
      <div data-datepicker data-value="{{ $date }}" data-go="{{ route('appointments.index', ['view' => $view]) }}"></div>
      <a class="btn sm" href="{{ route('appointments.index', ['date' => $shift(1), 'view' => $view]) }}" aria-label="Next">›</a>
      <div class="pills">@foreach (['day', 'week', 'month'] as $v)<a class="{{ $view === $v ? 'on' : '' }}" href="{{ route('appointments.index', ['date' => $date, 'view' => $v]) }}">{{ ucfirst($v) }}</a>@endforeach</div>
    </div>
    <a class="btn pri" href="{{ route('appointments.create', ['date' => max($date, $today)]) }}">Book appointment</a>
  </div>
</div>

@if ($view === 'day')
<div class="tw"><table>
  <thead><tr><th>Time</th><th>Patient</th><th>Treatment</th><th>Chair</th><th>Status</th></tr></thead>
  <tbody>
  @forelse ($appts as $a)
    <tr class="{{ $a->status === 'CANCELLED' ? 'off' : '' }}">
      <td class="mono">{{ Fmt::time($a->start_min) }}</td>
      <td><a href="{{ route('patients.show', $a->patient_id) }}"><b>{{ $a->patient->name }}</b></a><br><small class="mono muted">{{ $a->patient->code }}</small></td>
      <td>{{ $a->treatment ?? '—' }}@if ($a->source === 'ONLINE') <span class="chip c-acc">Online</span>@endif @if ($a->doctor)<br><small class="muted">{{ $a->doctor->name }}</small>@endif</td>
      <td>{{ $a->chair?->name ?? '—' }}</td>
      <td>
        <div class="row" style="flex-wrap:nowrap">
          @if ($a->status === 'CANCELLED')
            <span class="chip c-bad">Cancelled</span>
          @else
            <select class="inp" style="width:auto;padding:4px 8px" aria-label="Status" data-status="{{ route('appointments.status', $a) }}">
              @foreach (['BOOKED', 'WAITING', 'IN_CHAIR', 'COMPLETED', 'NO_SHOW'] as $s)<option value="{{ $s }}" @selected($a->status === $s)>{{ Status::label($s) }}</option>@endforeach
            </select>
          @endif
          <button type="button" class="btn sm" data-move="{{ route('appointments.move', $a) }}" data-id="{{ $a->id }}" data-date="{{ $a->date }}" data-chair="{{ $a->chair_id }}">Reschedule</button>
          @if ($a->status !== 'CANCELLED')
            <form method="post" action="{{ route('appointments.status', $a) }}" data-confirm="Cancel {{ $a->patient->name }}'s appointment at {{ Fmt::time($a->start_min) }}?">@csrf<input type="hidden" name="status" value="CANCELLED"><button class="btn sm danger">Cancel</button></form>
          @endif
          @if ($admin)
            <form method="post" action="{{ route('appointments.destroy', $a) }}" data-confirm="Delete permanently? This cannot be undone.">@csrf @method('DELETE')<button class="btn sm danger" title="Admin only">Delete</button></form>
          @endif
        </div>
      </td>
    </tr>
  @empty
    <tr><td colspan="5" class="muted" style="text-align:center;padding:30px">No appointments on this day.</td></tr>
  @endforelse
  </tbody>
</table></div>
<template id="move-tpl">
  <div class="panel stack" style="min-width:280px">
    <div class="row"><div data-mv-date></div><select class="inp" style="width:auto" aria-label="Chair" data-mv-chair>@foreach ($chairs as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div data-mv-slots></div>
    <div class="err hide" data-mv-err></div>
    <div class="row"><button type="button" class="btn sm pri" data-mv-save disabled>Move here</button><button type="button" class="btn sm" data-mv-close>Back</button></div>
  </div>
</template>
@endif

@if ($view === 'week')
<div class="tw"><div class="week">
  @for ($i = 0; $i < 7; $i++)
    @php($d = Fmt::addDays($from, $i))
    @php($list = $appts->where('date', $d))
    <div class="day">
      <a class="dh {{ $d === $today ? 'now' : '' }}" href="{{ route('appointments.index', ['date' => $d]) }}"><b>{{ date('D, j M', strtotime($d)) }}</b><br><small class="muted">{{ $list->where('status', '!=', 'CANCELLED')->count() }} appointments</small></a>
      <div class="stack" style="gap:5px;padding:6px">
        @foreach ($list as $a)
          <div class="ev {{ $a->status === 'CANCELLED' ? 'off' : '' }}"><span class="mono">{{ Fmt::time($a->start_min) }}</span> {{ $a->patient->name }}<br><small class="muted">{{ $a->treatment }} · {{ $a->chair?->name }} · {{ Status::label($a->status) }}</small></div>
        @endforeach
      </div>
    </div>
  @endfor
</div></div>
@endif

@if ($view === 'month')
<p class="muted" style="margin-top:-6px">Dates in <b style="color:var(--ink)">bold</b> have one or more appointments. Point at a date to see who is booked, or tap it to open that day.</p>
<div class="month">
  @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $w)<div class="wd">{{ $w }}</div>@endforeach
  @for ($i = 1; $i < (int) date('N', strtotime($from)); $i++)<div class="blank"></div>@endfor
  @for ($i = 1; $i <= (int) substr($to, 8); $i++)
    @php($d = substr($from, 0, 8).str_pad($i, 2, '0', STR_PAD_LEFT))
    @php($dayAppts = $live->where('date', $d))
    @php($n = $dayAppts->count())
    <a class="{{ $n ? 'has' : '' }}" href="{{ route('appointments.index', ['date' => $d]) }}" @if ($n) data-mtip aria-describedby="mt-{{ $i }}" @endif><span class="n {{ $d === $today ? 'now' : '' }}">{{ $i }}</span>@if ($n)<div style="font-size:12px">{{ $n }} appt{{ $n > 1 ? 's' : '' }}</div>
      <div class="mtip" id="mt-{{ $i }}" role="tooltip"><b>{{ Fmt::date($d) }}</b>@foreach ($dayAppts as $a)<span><i class="mono">{{ Fmt::time($a->start_min) }}</i>{{ $a->patient?->name }}<small>{{ $a->treatment }}{{ $a->chair ? ' · '.$a->chair->name : '' }}</small></span>@endforeach</div>@endif</a>
  @endfor
</div>
@endif
@endsection
