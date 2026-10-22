@extends('layouts.app')
@use('App\Support\Rx')
@php($me = auth()->user())
@section('title', 'Medicine library · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Medicine library</h1><p>Names suggested as you type a medicine on a visit, with the dose that fills in. New medicines written on a visit are added here on their own.</p></div>
  <div class="row"><a class="btn" href="{{ route('rx.templates') }}">Prescription templates</a><a class="btn" href="{{ route('rx.index') }}">Back to prescriptions</a></div>
</div>
<form method="post" action="{{ route('rx.medicines.store') }}" class="card" style="margin-bottom:16px">
  @csrf
  <h2>Add a medicine</h2>
  <div class="form medadd">
    <div class="field"><label for="mn">Name and strength</label><input id="mn" name="name" class="inp" maxlength="150" required placeholder="e.g. Cefuroxime 500 mg" value="{{ old('name') }}"></div>
    <div class="field"><label for="mf">Form</label><input id="mf" name="form" class="inp" list="rx-forms" maxlength="20" value="{{ old('form') }}"></div>
    <div class="field"><label for="md">Dose</label><input id="md" name="dose" class="inp mono" maxlength="40" placeholder="1-0-1" value="{{ old('dose') }}"></div>
    <div class="field"><label for="mt">When</label><input id="mt" name="timing" class="inp" list="rx-timings" maxlength="60" value="{{ old('timing') }}"></div>
    <div class="field"><label for="my">Days</label><input id="my" name="days" class="inp" maxlength="30" placeholder="5 days" value="{{ old('days') }}"></div>
  </div>
  <div class="row" style="margin-top:12px"><button class="btn pri">+ Add to library</button></div>
</form>
<datalist id="rx-forms">@foreach (Rx::FORMS as $f)<option value="{{ $f }}">@endforeach</datalist>
<datalist id="rx-timings">@foreach (Rx::TIMINGS as $f)<option value="{{ $f }}">@endforeach</datalist>
<div class="card">
  <div class="row" style="justify-content:space-between;margin-bottom:12px"><h2 style="margin:0">{{ $all->count() }} medicines</h2><input class="inp" style="max-width:280px" type="search" placeholder="Search the library" aria-label="Search the library" data-medfind></div>
  <div class="tw"><table class="lines meds medlib">
    <thead><tr><th>Medicine</th><th>Form</th><th>Dose</th><th>When</th><th>Days</th><th style="width:70px">Used</th><th style="width:70px">On</th>@if ($me->isAdmin())<th></th>@endif</tr></thead>
    <tbody>
    @forelse ($all as $m)
      <tr class="{{ $m->active ? '' : 'off' }}" data-med="{{ route('rx.medicines.update', $m) }}" data-name="{{ mb_strtolower($m->name) }}">
        <td style="min-width:220px"><input class="inp" data-f="name" value="{{ $m->name }}" maxlength="150" aria-label="Medicine"></td>
        <td style="width:120px"><input class="inp" data-f="form" value="{{ $m->form }}" list="rx-forms" maxlength="20" placeholder="Form" aria-label="Form"></td>
        <td style="width:110px"><input class="inp mono" data-f="dose" value="{{ $m->dose }}" maxlength="40" placeholder="Dose" aria-label="Dose"></td>
        <td style="width:150px"><input class="inp" data-f="timing" value="{{ $m->timing }}" list="rx-timings" maxlength="60" placeholder="When" aria-label="When"></td>
        <td style="width:100px"><input class="inp" data-f="days" value="{{ $m->days }}" maxlength="30" placeholder="Days" aria-label="Days"></td>
        <td class="muted mono">{{ $m->uses }}×</td>
        <td><button type="button" class="toggle {{ $m->active ? 'on' : '' }}" aria-pressed="{{ $m->active ? 'true' : 'false' }}" aria-label="Suggest {{ $m->name }}" data-toggle="{{ route('rx.medicines.active', $m) }}"></button></td>
        @if ($me->isAdmin())<td style="text-align:right"><form method="post" action="{{ route('rx.medicines.destroy', $m) }}" data-confirm="Delete {{ $m->name }} permanently? Old prescriptions keep it.">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form></td>@endif
      </tr>
    @empty
      <tr><td colspan="8" class="muted" style="text-align:center;padding:30px">No medicines yet. Add the first one above.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <p class="hint">Changes save on their own. Turned-off medicines are not suggested but stay on old prescriptions.</p>
</div>
@endsection
