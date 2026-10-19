@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Http\Controllers\PatientController')
@section('title', 'Patients · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Patients</h1><p>{{ number_format($total) }} in the clinic{{ $term !== '' ? ' · '.$rows->count().' match' : '' }}</p></div>
  <div class="row">
    @if (auth()->user()->canOpen('import'))<a class="btn" href="{{ route('import.show') }}">Upload old patients</a>@endif
    <a class="btn pri" href="{{ route('patients.create') }}">Add patient</a>
  </div>
</div>
<form class="card row" style="margin-bottom:12px">
  <input name="q" value="{{ $term }}" class="inp" style="flex:1;min-width:220px" placeholder="Search by name, mobile, ID or relative" aria-label="Search patients">
  <select name="by" class="inp" style="width:auto" aria-label="Search in">@foreach (PatientController::SCOPES as $k => $l)<option value="{{ $k }}" @selected($by === $k)>{{ $l }}</option>@endforeach</select>
  <button class="btn pri">Search</button>
</form>
<div class="tw"><table>
  <thead><tr><th>ID</th><th>Patient</th><th>Mobile</th><th>Relative</th><th>Added</th><th>Active</th></tr></thead>
  <tbody>
  @forelse ($rows as $p)
    <tr class="{{ $p->active ? '' : 'off' }}">
      <td class="mono">{{ $p->code }}</td>
      <td><a href="{{ route('patients.show', $p) }}" class="who" style="color:inherit;text-decoration:none">@include('partials.avatar', ['name' => $p->name])<div><b>{{ $p->name }}</b><small>{{ implode(' · ', array_filter([$p->age !== null ? "$p->age yrs" : null, $p->gender])) }}</small></div></a></td>
      <td class="mono">{{ Fmt::showMobile($p->mobile) }}</td>
      <td>{!! $p->relativeLabel() !== null ? e($p->relativeLabel()) : '<span class="muted">—</span>' !!}</td>
      <td class="muted">{{ $p->created_at->format('d M Y') }}</td>
      <td><button type="button" class="toggle {{ $p->active ? 'on' : '' }}" aria-pressed="{{ $p->active ? 'true' : 'false' }}" aria-label="Turn {{ $p->name }} on or off" data-toggle="{{ route('patients.active', $p) }}"></button></td>
    </tr>
  @empty
    <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">{{ $term !== '' ? "No patient matches \"$term\". Check the spelling or search a different field." : 'No patients yet. Add one or upload your old patients file.' }}</td></tr>
  @endforelse
  </tbody>
</table></div>
@endsection
