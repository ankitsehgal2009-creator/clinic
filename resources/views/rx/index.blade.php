@extends('layouts.app')
@use('App\Support\Fmt')
@section('title', 'Prescriptions · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Prescriptions</h1><p>Each visit keeps the doctor's notes and the prescription printed from it. {{ $today }} {{ $today === 1 ? 'visit' : 'visits' }} today.</p></div>
  <div class="row"><a class="btn" href="{{ route('rx.procedures') }}">Procedures</a><a class="btn" href="{{ route('rx.templates') }}">Templates</a><a class="btn pri" href="{{ route('rx.create') }}">+ New visit</a></div>
</div>
<form class="row" style="margin-bottom:14px"><input name="q" class="inp" style="max-width:340px" value="{{ $term }}" placeholder="Search by patient name, ID or mobile" aria-label="Search"><button class="btn">Search</button>@if ($term)<a class="btn" href="{{ route('rx.index') }}">Clear</a>@endif</form>
<div class="tw"><table>
  <thead><tr><th>Date</th><th>Patient</th><th>Visit</th><th>Doctor</th><th style="text-align:center">Medicines</th><th></th></tr></thead>
  <tbody>
  @forelse ($rows as $v)
    <tr>
      <td style="white-space:nowrap">{{ Fmt::date($v->date) }}</td>
      <td><a href="{{ route('patients.show', $v->patient_id) }}">{{ $v->patient?->name }}</a><br><small class="mono muted">{{ $v->patient?->code }}</small></td>
      <td>{{ $v->procedure ?: $v->complaint ?: '—' }}@if ($v->teeth)<br><small class="muted">Tooth {{ $v->teeth }}</small>@endif</td>
      <td class="muted">{{ $v->doctor?->name ?? '—' }}</td>
      <td style="text-align:center" class="mono">{{ $v->medicines_count }}</td>
      <td style="text-align:right"><a class="btn sm" href="{{ route('rx.show', $v) }}">Open</a></td>
    </tr>
  @empty
    <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">{{ $term ? 'No visits match "'.$term.'".' : 'No visits yet. Start with "New visit".' }}</td></tr>
  @endforelse
  </tbody>
</table></div>
@endsection
