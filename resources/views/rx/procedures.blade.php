@extends('layouts.app')
@php($me = auth()->user())
@section('title', 'Procedures · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Procedures</h1><p>The list doctors pick from in "Procedure done" on a visit. Add your own, or turn off the ones you never use.</p></div>
  <div class="row"><a class="btn" href="{{ route('rx.medicines') }}">Medicine library</a><a class="btn" href="{{ route('rx.templates') }}">Prescription templates</a><a class="btn" href="{{ route('rx.index') }}">Back to prescriptions</a></div>
</div>
<form method="post" action="{{ route('rx.procedures.store') }}" class="card row" style="margin-bottom:16px;flex-wrap:nowrap">
  @csrf
  <input name="name" class="inp" style="flex:1;max-width:420px" maxlength="120" required placeholder="New procedure, e.g. Gum surgery (flap)" aria-label="New procedure" autofocus>
  <button class="btn pri">+ Add</button>
</form>
<div class="tw"><table>
  <thead><tr><th>Procedure</th><th style="width:90px">On</th>@if ($me->isAdmin())<th style="width:90px"></th>@endif</tr></thead>
  <tbody>
  @forelse ($all as $pr)
    <tr class="{{ $pr->active ? '' : 'off' }}">
      <td>{{ $pr->name }}</td>
      <td><button type="button" class="toggle {{ $pr->active ? 'on' : '' }}" aria-pressed="{{ $pr->active ? 'true' : 'false' }}" aria-label="Use {{ $pr->name }}" data-toggle="{{ route('rx.procedures.active', $pr) }}"></button></td>
      @if ($me->isAdmin())<td style="text-align:right"><form method="post" action="{{ route('rx.procedures.destroy', $pr) }}" data-confirm="Delete {{ $pr->name }} permanently? Old visits keep it.">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form></td>@endif
    </tr>
  @empty
    <tr><td colspan="3" class="muted" style="text-align:center;padding:30px">No procedures yet. Add the first one above.</td></tr>
  @endforelse
  </tbody>
</table></div>
<p class="hint">Treatments in your Billing price list also show in the suggestions. Turned-off procedures stay on old visits.</p>
@endsection
