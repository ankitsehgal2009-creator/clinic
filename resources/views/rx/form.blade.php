@extends('layouts.app')
@use('App\Support\Rx')
@php($me = auth()->user())
@php($oldMeds = old('meds', $visit ? $visit->medicines->map(fn ($m) => $m->only(['name', 'form', 'dose', 'timing', 'days']))->all() : null))
@section('title', ($visit ? 'Edit visit' : 'New visit').' · SmileDesk')
@section('content')
<div class="ph"><div><h1>{{ $visit ? 'Edit visit' : 'New visit & prescription' }}</h1><p>Write the visit notes, pick a template or type the medicines, then save and print.</p></div><a class="btn" href="{{ $visit ? route('rx.show', $visit) : route('rx.index') }}">Back</a></div>
<form id="rxform" method="post" action="{{ $visit ? route('rx.update', $visit) : route('rx.store') }}" class="stack"
  data-templates="{{ json_encode($tplData) }}" data-old="{{ json_encode($oldMeds) }}" data-default="{{ $visit ? '' : $defaultTpl }}">
  @csrf
  <input type="hidden" name="patient_id" value="{{ old('patient_id', $patient?->id) }}" data-pid>
  <div class="card form">
    <div class="field">
      <label for="pq">Patient</label>
      <input id="pq" class="inp" autocomplete="off" placeholder="Type at least 3 letters of the name" value="{{ old('patient_name', $patient?->name) }}" name="patient_name" role="combobox" aria-expanded="false" aria-controls="pq-list" data-search="/api/rx/patients" @disabled($visit)>
      <div id="pq-list" class="sugg hide" role="listbox"></div>
      <span class="chip c-ok {{ $patient ? '' : 'hide' }}" style="align-self:flex-start" data-picked>{{ $patient ? $patient->code.' · '.$patient->mobile : '' }}</span>
      <span class="hint {{ $patient ? 'hide' : '' }}" data-hint>Suggestions come from registered patients.</span>
    </div>
    <div class="field"><span class="lbl">Visit date</span><div data-datepicker data-plain data-name="date" data-value="{{ old('date', $visit->date ?? $today) }}" data-max="{{ $today }}"></div></div>
    <div class="field"><label for="doc">Doctor</label><select id="doc" name="doctor_id" class="inp">
      @foreach ($doctors as $d)<option value="{{ $d->id }}" @selected((int) old('doctor_id', $visit->doctor_id ?? ($me->role === 'DOCTOR' ? $me->id : $doctors->first()?->id)) === $d->id)>{{ $d->name }}</option>@endforeach
    </select></div>
  </div>

  <div class="card">
    <h2>Visit notes</h2>
    <div class="form">
      <div class="field full"><label for="cc">Chief complaint</label><input id="cc" name="complaint" class="inp" maxlength="250" value="{{ old('complaint', $visit->complaint ?? '') }}" placeholder="e.g. Pain in lower left back tooth for 3 days"></div>
      <div class="field"><label for="tt">Tooth</label><input id="tt" name="teeth" class="inp mono" maxlength="60" value="{{ old('teeth', $visit->teeth ?? $tooth) }}" placeholder="e.g. 36, 37"><span class="hint">FDI numbers. The tooth chart shows this visit under each tooth.</span></div>
      <div class="field"><label for="pr">Procedure done</label><input id="pr" name="procedure" class="inp" list="proc-names" maxlength="250" value="{{ old('procedure', $visit->procedure ?? '') }}" placeholder="Pick or type"><datalist id="proc-names">@foreach ($procedures as $p)<option value="{{ $p }}">@endforeach</datalist></div>
      <div class="field full"><label for="fd">Findings</label><textarea id="fd" name="findings" class="inp" rows="3" maxlength="3000">{{ old('findings', $visit->findings ?? '') }}</textarea><span class="hint">Kept in the patient's record. Not printed on the prescription.</span></div>
      <div class="field"><label for="dg">Diagnosis</label><input id="dg" name="diagnosis" class="inp" maxlength="250" value="{{ old('diagnosis', $visit->diagnosis ?? '') }}" placeholder="e.g. Irreversible pulpitis"></div>
      <div class="field"><span class="lbl">Next visit</span><div data-datepicker data-clearable data-name="next_visit" data-placeholder="No next visit" data-value="{{ old('next_visit', $visit->next_visit ?? '') }}" data-min="{{ $today }}"></div></div>
    </div>
  </div>

  <div class="card">
    <div class="row" style="justify-content:space-between;margin-bottom:12px">
      <h2 style="margin:0">Prescription</h2>
      <div class="row"><label for="tpl" class="muted">Template</label>
        <select id="tpl" name="rx_template_id" class="inp" style="width:auto" data-tpl>
          <option value="">None</option>
          @foreach ($templates as $t)<option value="{{ $t->id }}" @selected((string) old('rx_template_id', $visit->rx_template_id ?? '') === (string) $t->id)>{{ $t->name }}{{ $defaultTpl === $t->id ? ' (default)' : '' }}</option>@endforeach
        </select></div>
    </div>
    <datalist id="rx-forms">@foreach (Rx::FORMS as $f)<option value="{{ $f }}">@endforeach</datalist>
    <datalist id="rx-timings">@foreach (Rx::TIMINGS as $f)<option value="{{ $f }}">@endforeach</datalist>
    <div class="tw"><table class="lines"><thead><tr><th>Medicine</th><th>Form</th><th>Dose</th><th>When</th><th>Days</th><th></th></tr></thead><tbody data-meds></tbody></table></div>
    <template id="med-tpl"><tr>
      <td style="min-width:220px"><input class="inp" data-f="name" placeholder="e.g. Amoxicillin 500 mg" aria-label="Medicine"></td>
      <td style="width:120px"><input class="inp" data-f="form" list="rx-forms" aria-label="Form"></td>
      <td style="width:110px"><input class="inp mono" data-f="dose" placeholder="1-0-1" aria-label="Dose"></td>
      <td style="width:150px"><input class="inp" data-f="timing" list="rx-timings" aria-label="When"></td>
      <td style="width:100px"><input class="inp" data-f="days" placeholder="5 days" aria-label="Days"></td>
      <td><button type="button" class="btn sm" data-del aria-label="Remove medicine">✕</button></td>
    </tr></template>
    <button type="button" class="btn" style="margin-top:12px" data-add>+ Add medicine</button>
    <p class="hint">Dose is morning-afternoon-night, so 1-0-1 means one in the morning and one at night.</p>
    <div class="field" style="margin-top:8px"><label for="adv">Advice printed below the medicines</label><textarea id="adv" name="advice" class="inp" rows="2" maxlength="2000" data-advice>{{ old('advice', $visit->advice ?? '') }}</textarea></div>
    <div class="field" style="margin-top:12px;max-width:320px"><label for="ds">Print design</label><select id="ds" name="design" class="inp">@foreach (Rx::DESIGNS as $k => [$n, $why])<option value="{{ $k }}" @selected(old('design', $visit->design ?? $me->clinic->rx_design) === $k)>{{ $n }}{{ $me->clinic->rx_design === $k ? ' (clinic default)' : '' }}</option>@endforeach</select></div>
  </div>
  <div class="row"><button class="btn pri" data-save>{{ $visit ? 'Save changes' : 'Save and print' }}</button>@unless ($visit)<span class="hint">After saving you can add photos of a handwritten prescription and X-rays.</span>@endunless</div>
</form>
@if ($visit)@include('rx.photos')@endif
@endsection
@push('scripts')<script>window.NEW_PATIENT_URL = @json(route('patients.create'));</script>@endpush
