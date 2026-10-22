@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Status')
@php($me = auth()->user())
@section('title', 'Dashboard · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Hello, {{ implode(' ', array_slice(explode(' ', $me->name), 0, str_starts_with($me->name, 'Dr.') ? 2 : 1)) }}</h1><p>{{ Fmt::date($today) }}</p></div>
  @if ($me->canOpen('appointments'))<a class="btn pri" href="{{ route('appointments.create') }}">Book appointment</a>@endif
</div>
@if ($me->canOpen('patients'))
<form class="card form dash-find" id="dashpick" data-go="{{ url('/patients') }}" style="margin-bottom:16px" onsubmit="return false">
  <input type="hidden" data-pid>
  <div class="field">
    <label for="pq">Open a patient file</label>
    <input id="pq" class="inp" autocomplete="off" placeholder="Type 3 letters of the name, mobile or patient ID" role="combobox" aria-expanded="false" aria-controls="pq-list" data-search="/api/dash/patients">
    <div id="pq-list" class="sugg hide" role="listbox"></div>
    <span class="chip c-ok hide" style="align-self:flex-start" data-picked></span>
    <span class="hint" data-hint>Pick a name to open their file: details, visits, tooth chart and bills.</span>
  </div>
</form>
@endif
<div class="grid g4" style="margin-bottom:16px">
  <div class="card kpi"><span class="l">Today's appointments</span><span class="v">{{ $live }}</span><span class="muted">{{ $count('COMPLETED') }} done · {{ $count('WAITING') }} waiting</span></div>
  <div class="card kpi"><span class="l">In chair now</span><span class="v">{{ $count('IN_CHAIR') }}</span></div>
  <div class="card kpi"><span class="l">New patients this month</span><span class="v">{{ $newThisMonth }}</span></div>
  @if ($collected !== null)<a class="card kpi" href="{{ route('billing.index') }}" style="color:inherit;text-decoration:none"><span class="l">Collected today</span><span class="v">{{ \App\Support\Money::inr($collected) }}</span></a>@endif
  <div class="card kpi"><span class="l">All patients</span><span class="v">{{ number_format($total) }}</span></div>
</div>
<div class="card">
  <h2>Today's schedule</h2>
  @if ($appts->isNotEmpty())
    <div class="tw"><table><tbody>
      @foreach ($appts as $a)
        <tr class="{{ $a->status === 'CANCELLED' ? 'off' : '' }}"><td class="mono" style="width:90px">{{ Fmt::time($a->start_min) }}</td><td>@if ($me->canOpen('patients'))<a class="pt-link" href="{{ route('patients.show', $a->patient_id) }}"><b>{{ $a->patient->name }}</b></a>@else<b>{{ $a->patient->name }}</b>@endif<br><small class="muted">{{ $a->patient->code }} · {{ $a->treatment }} · {{ $a->chair?->name }}</small></td><td style="text-align:right;white-space:nowrap"><span class="chip {{ Status::cls($a->status) }}">{{ Status::label($a->status) }}</span>@if ($me->canOpen('patients')) <a class="btn sm" href="{{ route('patients.show', $a->patient_id) }}">Open file</a>@endif @if ($me->canOpen('prescriptions') && $a->status !== 'CANCELLED') <a class="btn sm" href="{{ route('rx.create', ['patient' => $a->patient_id]) }}">Rx</a>@endif</td></tr>
      @endforeach
    </tbody></table></div>
  @else
    <p class="muted">No appointments today yet.</p>
  @endif
  @if ($me->canOpen('appointments'))<p style="margin-bottom:0"><a href="{{ route('appointments.index') }}">Open the calendar</a></p>@endif
</div>
@endsection
@push('scripts')<script>window.NEW_PATIENT_URL = @json(route('patients.create'));</script>@endpush
