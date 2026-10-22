{{-- Day calendar: one column per chair, one row per slot. Free future slots book in place. --}}
@use('App\Support\Fmt')
@use('App\Support\Status')
@php
  $step = $clinic->slot_minutes;
  $slots = $clinic->slots();
  $rowOf = []; $breaks = []; $row = 2; $prev = null;
  foreach ($slots as $m) {
      if ($prev !== null && $m - $prev > $step) { $breaks[] = [$row, $prev + $step, $m]; $row++; }
      $rowOf[$m] = $row++; $prev = $m;
  }
  $past = fn ($m) => $date < $today || ($date === $today && $m < $nowMin);
  // Place each live appointment on its chair at its first slot; it covers the slots its length needs.
  $blocks = []; $covered = []; $offGrid = [];
  foreach ($live as $a) {
      $start = collect($slots)->last(fn ($m) => $m <= $a->start_min);
      if ($start === null || ! $chairs->contains('id', $a->chair_id) || $a->start_min >= $start + $step) { $offGrid[] = $a; continue; }
      $key = $a->chair_id.'-'.$start;
      if (isset($covered[$key]) && ! isset($blocks[$covered[$key]])) { $offGrid[] = $a; continue; }
      $head = $covered[$key] ?? $key;
      $blocks[$head]['appts'][] = $a;
      $blocks[$head]['chair'] = $a->chair_id; $blocks[$head]['start'] = $blocks[$head]['start'] ?? $start;
      $span = 0;
      foreach ($slots as $m) {
          if ($m < $start) continue;
          if ($m >= $a->start_min + max($a->duration, 1) || ($span && ! isset($rowOf[$m - $step]))) break;
          $covered[$a->chair_id.'-'.$m] = $head; $span++;
      }
      $blocks[$head]['span'] = max($blocks[$head]['span'] ?? 1, $span);
  }
  $cancelled = $appts->where('status', 'CANCELLED')->count();
@endphp
@if ($chairs->isEmpty())
  <div class="card muted">Add a chair in Settings to use the calendar.</div>
@else
<div class="tw cal-wrap">
  <div class="cal" style="--n:{{ $chairs->count() }};grid-template-columns:76px repeat({{ $chairs->count() }}, minmax(150px, 1fr))">
    <div class="cal-h cal-t" style="grid-row:1;grid-column:1"></div>
    @foreach ($chairs as $ci => $c)<div class="cal-h" style="grid-row:1;grid-column:{{ $ci + 2 }}">{{ $c->name }}</div>@endforeach
    @foreach ($breaks as [$r, $from, $to])
      <div class="cal-break" style="grid-row:{{ $r }};grid-column:1 / -1">Closed {{ Fmt::time($from) }} to {{ Fmt::time($to) }}</div>
    @endforeach
    @foreach ($slots as $m)
      <div class="cal-time {{ $m % 60 === 0 ? 'hr' : '' }}" style="grid-row:{{ $rowOf[$m] }};grid-column:1">{{ Fmt::time($m) }}</div>
      @foreach ($chairs as $ci => $c)
        @php($key = $c->id.'-'.$m)
        @if (isset($blocks[$key]))
          @php($b = $blocks[$key])
          <div class="cal-appts" style="grid-row:{{ $rowOf[$m] }} / span {{ $b['span'] }};grid-column:{{ $ci + 2 }}">
            @foreach ($b['appts'] as $a)
              <div class="cal-appt s-{{ strtolower($a->status) }}">
                <a href="{{ route('patients.show', $a->patient_id) }}" class="cal-name">{{ $a->patient->name }}</a>
                <small><span class="mono">{{ Fmt::time($a->start_min) }}</span>{{ $a->treatment ? ' · '.$a->treatment : '' }}</small>
                <span class="row" style="gap:4px"><span class="chip {{ Status::cls($a->status) }}">{{ Status::label($a->status) }}</span>@if ($a->source === 'ONLINE')<span class="chip c-acc">Online</span>@endif</span>
              </div>
            @endforeach
          </div>
        @elseif (! isset($covered[$key]))
          @if ($past($m))
            <div class="cal-cell past" style="grid-row:{{ $rowOf[$m] }};grid-column:{{ $ci + 2 }}"></div>
          @else
            <button type="button" class="cal-cell free" style="grid-row:{{ $rowOf[$m] }};grid-column:{{ $ci + 2 }}" data-cal-book data-chair="{{ $c->id }}" data-chair-name="{{ $c->name }}" data-min="{{ $m }}" aria-label="Book {{ $c->name }} at {{ Fmt::time($m) }}"><span>+ {{ Fmt::time($m) }}</span></button>
          @endif
        @endif
      @endforeach
    @endforeach
  </div>
</div>
@if ($offGrid || $cancelled)
  <p class="hint" style="margin-top:10px">
    @foreach ($offGrid as $a)<a href="{{ route('patients.show', $a->patient_id) }}">{{ $a->patient->name }}</a> at {{ Fmt::time($a->start_min) }} ({{ $a->chair?->name ?? 'no chair' }}) is outside the calendar. @endforeach
    @if ($cancelled){{ $cancelled }} cancelled {{ $cancelled === 1 ? 'appointment is' : 'appointments are' }} hidden here; <a href="{{ route('appointments.index', ['date' => $date, 'layout' => 'list']) }}">see List</a>.@endif
  </p>
@endif

<template id="calbook-tpl">
  <form class="dlg calbook" autocomplete="off">
    <h2 style="margin-bottom:4px">Book appointment</h2>
    <p class="muted" style="margin:0 0 14px" data-when></p>
    <div class="stack" style="gap:12px">
      <div class="field" data-pick-part>
        <label for="cb-q">Patient</label>
        <input id="cb-q" class="inp" placeholder="Type 3 letters of the name, mobile or ID" role="combobox" aria-expanded="false" aria-controls="cb-list" data-search="/api/patients/search">
        <div id="cb-list" class="sugg hide" role="listbox"></div>
        <input type="hidden" data-pid>
        <span class="chip c-ok hide" style="align-self:flex-start" data-picked></span>
        <span class="hint" data-hint>Pick a registered patient @if (auth()->user()->canOpen('patients')) or <button type="button" class="linkbtn pri" data-new>add a new patient</button>@endif.</span>
      </div>
      @if (auth()->user()->canOpen('patients'))
      <div class="stack hide" style="gap:12px" data-new-part>
        <div class="row" style="justify-content:space-between"><b>New patient</b><button type="button" class="linkbtn pri" data-old>Pick a registered patient instead</button></div>
        <div class="form" style="grid-template-columns:1fr 1fr">
          <div class="field"><label for="cb-n">Full name</label><input id="cb-n" class="inp" maxlength="150" data-np="name"></div>
          <div class="field"><label for="cb-m">Mobile</label><input id="cb-m" class="inp mono" inputmode="tel" maxlength="15" data-np="mobile"></div>
          <div class="field"><label for="cb-rt">Relative</label><select id="cb-rt" class="inp" data-np="relation_type"><option value="">None</option><option value="FATHER">Father</option><option value="HUSBAND">Husband</option><option value="WIFE">Wife</option><option value="MOTHER">Mother</option></select></div>
          <div class="field"><label for="cb-rn">Relative's name</label><input id="cb-rn" class="inp" maxlength="150" data-np="relative_name"></div>
        </div>
        <span class="hint">You can add the address, age and medical history later from the patient file.</span>
      </div>
      @endif
      <div class="form" style="grid-template-columns:1fr 1fr">
        @if ($doctors->isNotEmpty())<div class="field"><label for="cb-d">Doctor</label><select id="cb-d" class="inp" data-doctor>@foreach ($doctors as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>@endif
        <div class="field"><label for="cb-t">Treatment</label><select id="cb-t" class="inp" data-treatment>@foreach (Status::TREATMENTS as $t)<option>{{ $t }}</option>@endforeach</select></div>
      </div>
      <div class="err hide" data-err></div>
    </div>
    <div class="row" style="justify-content:flex-end;margin-top:16px"><button type="button" class="btn" data-x>Cancel</button><button class="btn pri" data-go>Book</button></div>
  </form>
</template>
<div hidden data-calbook data-date="{{ $date }}" data-store="{{ route('appointments.store') }}" data-patient-store="{{ route('patients.store') }}"></div>
@endif
