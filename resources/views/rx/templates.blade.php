@extends('layouts.app')
@use('App\Support\Rx')
@php($me = auth()->user())
@section('title', 'Prescription templates · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Prescription templates</h1><p>Save common prescriptions once and load them with one tap. The default one loads first on every new visit.</p></div>
  <div class="row"><a class="btn" href="{{ route('rx.medicines') }}">Medicine library</a><a class="btn" href="{{ route('rx.index') }}">Back to prescriptions</a><a class="btn pri" href="{{ route('rx.templates', ['new' => 1]) }}">+ New template</a></div>
</div>
<div class="billgrid tplgrid">
  <div class="card" style="padding:8px">
    @forelse ($all as $t)
      <a class="tpl-row {{ $edit?->id === $t->id ? 'on' : '' }} {{ $t->active ? '' : 'off' }}" href="{{ route('rx.templates', ['t' => $t->id]) }}">
        <span class="star {{ $defaultTpl === $t->id ? 'on' : '' }}" aria-hidden="true">★</span>
        <span style="flex:1"><b>{{ $t->name }}</b><br><small class="muted">{{ count($t->meds ?? []) }} {{ count($t->meds ?? []) === 1 ? 'medicine' : 'medicines' }} · used {{ $t->uses }} {{ $t->uses === 1 ? 'time' : 'times' }}{{ $t->active ? '' : ' · off' }}</small></span>
        @if ($defaultTpl === $t->id)<span class="chip c-acc">Default</span>@endif
      </a>
    @empty
      <p class="muted" style="padding:10px">No templates yet.</p>
    @endforelse
  </div>
  <form id="tplform" method="post" action="{{ $edit ? route('rx.templates.update', $edit) : route('rx.templates.store') }}" class="card" data-old="{{ json_encode(old('meds', $edit?->meds ?? [])) }}">
    @csrf
    <div class="row" style="justify-content:space-between;margin-bottom:12px">
      <h2 style="margin:0">{{ $edit ? $edit->name : 'New template' }}</h2>
      @if ($edit)
        <div class="row">
          @if ($defaultTpl === $edit->id)<span class="chip c-acc">This is the default</span>@else<button type="submit" class="btn sm" form="tpl-default">★ Make default</button>@endif
          <span class="muted" style="font-size:13px">On</span><button type="button" class="toggle {{ $edit->active ? 'on' : '' }}" aria-pressed="{{ $edit->active ? 'true' : 'false' }}" aria-label="Use {{ $edit->name }}" data-toggle="{{ route('rx.templates.active', $edit) }}"></button>
        </div>
      @endif
    </div>
    <div class="field" style="max-width:360px"><label for="tn">Template name</label><input id="tn" name="name" class="inp" required maxlength="80" value="{{ old('name', $edit?->name) }}" placeholder="e.g. Post RCT"></div>
    <datalist id="rx-forms">@foreach (Rx::FORMS as $f)<option value="{{ $f }}">@endforeach</datalist>
    <datalist id="rx-timings">@foreach (Rx::TIMINGS as $f)<option value="{{ $f }}">@endforeach</datalist>
    <datalist id="rx-meds">@foreach ($medLib as $m)<option value="{{ $m->name }}" data-form="{{ $m->form }}" data-dose="{{ $m->dose }}" data-timing="{{ $m->timing }}" data-days="{{ $m->days }}">@endforeach</datalist>
    <div class="tw" style="margin-top:12px"><table class="lines meds"><thead><tr><th>Medicine</th><th>Form</th><th>Dose</th><th>When</th><th>Days</th><th></th></tr></thead><tbody data-meds></tbody></table></div>
    <template id="med-tpl"><tr>
      <td style="min-width:200px"><input class="inp" data-f="name" list="rx-meds" autocomplete="off" placeholder="e.g. Amoxicillin 500 mg" aria-label="Medicine"></td>
      <td style="width:115px"><input class="inp" data-f="form" list="rx-forms" placeholder="Form" aria-label="Form"></td>
      <td style="width:105px"><input class="inp mono" data-f="dose" placeholder="1-0-1" aria-label="Dose"></td>
      <td style="width:140px"><input class="inp" data-f="timing" list="rx-timings" placeholder="When" aria-label="When"></td>
      <td style="width:95px"><input class="inp" data-f="days" placeholder="5 days" aria-label="Days"></td>
      <td><button type="button" class="btn sm" data-del aria-label="Remove medicine">✕</button></td>
    </tr></template>
    <div class="row" style="margin-top:12px"><button type="button" class="btn" data-add>+ Add medicine</button><a class="btn" href="{{ route('rx.medicines') }}">Medicine library</a><label class="chk online-chk" title="Also look up medicine names that are not in your library"><input type="checkbox" data-online-meds> Search medicine names online too</label></div>
    <div class="field" style="margin-top:12px"><label for="ta">Advice printed below</label><textarea id="ta" name="advice" class="inp" rows="3" maxlength="2000">{{ old('advice', $edit?->advice) }}</textarea></div>
    <p class="hint">Dose is morning-afternoon-night, so 1-0-1 means one in the morning and one at night. Turn a template off to hide it from new visits.</p>
    <div class="row" style="margin-top:6px"><button class="btn pri">Save template</button></div>
  </form>
</div>
@if ($edit)
  <form id="tpl-default" method="post" action="{{ route('rx.templates.default', $edit) }}" class="hide">@csrf</form>
  @if ($me->isAdmin())
    <form method="post" action="{{ route('rx.templates.destroy', $edit) }}" data-confirm="Delete the template {{ $edit->name }} permanently? Old prescriptions keep their medicines." style="margin-top:14px">@csrf @method('DELETE')<button class="btn danger sm">Delete template permanently</button></form>
  @endif
@endif
@endsection
