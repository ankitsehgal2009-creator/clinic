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
<div class="grid g4" style="margin-bottom:16px">
  <div class="card kpi"><span class="l">Today's appointments</span><span class="v">{{ $live }}</span><span class="muted">{{ $count('COMPLETED') }} done · {{ $count('WAITING') }} waiting</span></div>
  <div class="card kpi"><span class="l">In chair now</span><span class="v">{{ $count('IN_CHAIR') }}</span></div>
  <div class="card kpi"><span class="l">New patients this month</span><span class="v">{{ $newThisMonth }}</span></div>
  <div class="card kpi"><span class="l">All patients</span><span class="v">{{ number_format($total) }}</span></div>
</div>
<div class="card">
  <h2>Today's schedule</h2>
  @if ($appts->isNotEmpty())
    <div class="tw"><table><tbody>
      @foreach ($appts as $a)
        <tr class="{{ $a->status === 'CANCELLED' ? 'off' : '' }}"><td class="mono" style="width:90px">{{ Fmt::time($a->start_min) }}</td><td><b>{{ $a->patient->name }}</b><br><small class="muted">{{ $a->treatment }} · {{ $a->chair?->name }}</small></td><td style="text-align:right"><span class="chip {{ Status::cls($a->status) }}">{{ Status::label($a->status) }}</span></td></tr>
      @endforeach
    </tbody></table></div>
  @else
    <p class="muted">No appointments today yet.</p>
  @endif
  @if ($me->canOpen('appointments'))<p style="margin-bottom:0"><a href="{{ route('appointments.index') }}">Open the calendar</a></p>@endif
</div>
@endsection
