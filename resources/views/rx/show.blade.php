@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Rx')
@section('title', 'Prescription · '.$visit->patient->name.' · SmileDesk')
@section('content')
<div class="ph noprint">
  <div><h1>{{ $visit->patient->name }} <span class="muted mono" style="font-size:1rem">{{ $visit->patient->code }}</span></h1>
    <p>Visit on {{ Fmt::date($visit->date) }}{{ $visit->doctor ? ' with '.$visit->doctor->name : '' }}</p></div>
  <div class="row">
    <a class="btn" href="{{ route('patients.show', $visit->patient) }}">Patient file</a>
    <a class="btn" href="{{ route('rx.edit', $visit) }}">Edit visit</a>
    <a class="btn" href="{{ route('rx.create', ['patient' => $visit->patient_id]) }}">New visit</a>
    <button type="button" class="btn pri" data-print=".rx" data-paper="A5">Print</button>
  </div>
</div>
<div class="billgrid">
  <div>
    <div class="pills noprint" style="margin-bottom:12px" role="group" aria-label="Print design">
      @foreach (Rx::DESIGNS as $k => [$n])<a href="?design={{ $k }}" class="{{ $design === $k ? 'on' : '' }}">{{ $n }}</a>@endforeach
    </div>
    @include('rx.paper', ['d' => $design, 'doc' => $visit->doctor, 'pt' => $visit->patient, 'v' => $visit->only(['date', 'complaint', 'teeth', 'procedure', 'diagnosis', 'advice', 'next_visit']), 'meds' => $visit->medicines->map(fn ($m) => $m->only(['name', 'form', 'dose', 'timing', 'days']))->all()])
  </div>
  <div class="card noprint">
    <h2>Visit notes</h2>
    <table class="totals" style="width:100%">
      <tr><td class="muted">Complaint</td><td style="text-align:left">{{ $visit->complaint ?: '—' }}</td></tr>
      <tr><td class="muted">Tooth</td><td style="text-align:left" class="mono">{{ $visit->teeth ?: '—' }}</td></tr>
      <tr><td class="muted">Procedure</td><td style="text-align:left">{{ $visit->procedure ?: '—' }}</td></tr>
      <tr><td class="muted">Diagnosis</td><td style="text-align:left">{{ $visit->diagnosis ?: '—' }}</td></tr>
      <tr><td class="muted">Next visit</td><td style="text-align:left">{{ $visit->next_visit ? Fmt::date($visit->next_visit) : '—' }}</td></tr>
    </table>
    @if ($visit->findings)<h2 style="margin-top:14px">Findings</h2><p style="white-space:pre-line;margin:0">{{ $visit->findings }}</p>@endif
    <p class="hint" style="margin-top:14px">Prescriptions print on A5 by default. The design buttons only change this print; the clinic default is set in Settings.</p>
    @if ($visit->next_visit && auth()->user()->canOpen('appointments'))<a class="btn" href="{{ route('appointments.create', ['patient' => $visit->patient_id, 'date' => $visit->next_visit]) }}">Book the next visit</a>@endif
  </div>
</div>
@include('rx.photos')
@endsection
