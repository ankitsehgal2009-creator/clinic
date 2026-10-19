@extends('layouts.app')
@use('App\Support\Fmt')
@section('title', 'Book appointment · SmileDesk')
@section('content')
<div class="ph"><div><h1>Book appointment</h1><p>Slots every {{ $clinic->slot_minutes }} minutes, {{ Fmt::time($clinic->morning_open) }} to {{ Fmt::time($clinic->morning_close) }} and {{ Fmt::time($clinic->evening_open) }} to {{ Fmt::time($clinic->evening_close) }}.</p></div></div>
<form method="post" action="{{ route('appointments.store') }}" class="card" style="max-width:720px" id="booking">
  @csrf
  <input type="hidden" name="patient_id" value="{{ old('patient_id', $patient?->id) }}" data-pid>
  <input type="hidden" name="start_min" value="" data-slot-value>
  <div class="form">
    <div class="field full">
      <label for="pq">Patient</label>
      <input id="pq" class="inp" autocomplete="off" placeholder="Type at least 3 letters of the name" value="{{ old('patient_name', $patient?->name) }}" name="patient_name" role="combobox" aria-expanded="false" aria-controls="pq-list" data-search="/api/patients/search">
      <div id="pq-list" class="sugg hide" role="listbox"></div>
      <span class="chip c-ok {{ $patient ? '' : 'hide' }}" style="align-self:flex-start" data-picked>{{ $patient ? $patient->code.' · '.$patient->mobile : '' }}</span>
      <span class="hint {{ $patient ? 'hide' : '' }}" data-hint>Suggestions come from registered patients.</span>
    </div>
    <div class="field"><span class="lbl">Date</span><div data-datepicker data-name="date" data-value="{{ old('date', $date) }}" data-min="{{ Fmt::today() }}"></div></div>
    <div class="field"><label for="ch">Chair</label><select id="ch" name="chair_id" class="inp">@foreach ($chairs as $c)<option value="{{ $c->id }}" @selected(old('chair_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    @if ($doctors->isNotEmpty())<div class="field"><label for="doc">Doctor</label><select id="doc" name="doctor_id" class="inp">@foreach ($doctors as $d)<option value="{{ $d->id }}" @selected(old('doctor_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>@endif
    <div class="field"><label for="tr">Treatment</label><select id="tr" name="treatment" class="inp">@foreach (\App\Support\Status::TREATMENTS as $t)<option @selected(old('treatment') === $t)>{{ $t }}</option>@endforeach</select></div>
    <div class="field full"><div data-slots></div></div>
  </div>
  <div class="row" style="margin-top:18px"><button class="btn pri" data-book disabled>Pick a patient and a free slot</button></div>
</form>
<script>window.NEW_PATIENT_URL = @json(route('patients.create'));</script>
@endsection
