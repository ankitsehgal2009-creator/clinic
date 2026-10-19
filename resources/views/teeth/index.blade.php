@extends('layouts.app')
@section('title', 'Tooth chart · SmileDesk')
@section('content')
<div class="ph"><div><h1>Tooth chart</h1><p>Find a patient to open their chart. Doctors mark each tooth, and every visit that names a tooth shows under it.</p></div>@if ($patient)<a class="btn" href="{{ route('patients.show', $patient) }}">Open patient file</a>@endif</div>
<form class="card form" style="margin-bottom:16px" id="teethpick" data-go="{{ route('teeth.index') }}">
  <input type="hidden" data-pid value="{{ $patient?->id }}">
  <div class="field">
    <label for="pq">Patient</label>
    <input id="pq" class="inp" autocomplete="off" placeholder="Type at least 3 letters of the name" value="{{ $patient?->name }}" role="combobox" aria-expanded="false" aria-controls="pq-list" data-search="/api/teeth/patients">
    <div id="pq-list" class="sugg hide" role="listbox"></div>
    <span class="chip c-ok {{ $patient ? '' : 'hide' }}" style="align-self:flex-start" data-picked>{{ $patient ? $patient->code.' · '.$patient->mobile : '' }}</span>
    <span class="hint {{ $patient ? 'hide' : '' }}" data-hint>Suggestions come from registered patients.</span>
  </div>
</form>
@if ($chart)
  @include('teeth.chart', ['chart' => $chart])
@endif
@endsection
@push('scripts')<script>window.NEW_PATIENT_URL = @json(route('patients.create'));</script>@endpush
