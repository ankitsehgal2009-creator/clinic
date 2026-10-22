{{-- Interactive tooth chart for one patient. $chart comes from ToothController::chartData() --}}
@use('App\Support\Teeth')
@php
  $p = $chart['patient'];
  $up = 'M4 30 C3 20 3 12 6 6 C9 2 19 2 22 6 C25 12 25 20 24 30 C22 33 19 33 17 26 C15 22 13 22 11 26 C9 33 6 33 4 30Z';
  $lo = 'M4 4 C3 14 3 22 6 28 C9 32 19 32 22 28 C25 22 25 14 24 4 C22 1 19 1 17 8 C15 12 13 12 11 8 C9 1 6 1 4 4Z';
  $jaw = function (array $teeth) use ($chart, $up, $lo) {
      $h = '<div class="jaw">';
      foreach ($teeth as $i => $n) {
          if ($i === count($teeth) / 2) $h .= '<span class="mid" aria-hidden="true"></span>';
          $st = $chart['marks'][$n]['state'] ?? 'healthy';
          $upper = in_array(intdiv($n, 10), [1, 2, 5, 6], true);
          $has = isset($chart['history'][$n]) ? ' hist' : '';
          $h .= '<button type="button" class="tooth st-'.$st.$has.'" data-n="'.$n.'" aria-label="Tooth '.$n.', '.e(Teeth::name($n)).', '.e(Teeth::STATES[$st]).'">'
             .'<svg viewBox="0 0 28 34" aria-hidden="true"><path class="tb" d="'.($upper ? $up : $lo).'"/><path class="tx" d="M7 9l14 16M21 9 7 25"/><path class="tr" d="M14 8v18"/></svg><span>'.$n.'</span></button>';
      }
      return $h.'</div>';
  };
  $names = [];
  foreach ([...Teeth::UPPER, ...Teeth::LOWER, ...Teeth::UPPER_CHILD, ...Teeth::LOWER_CHILD] as $n) $names[$n] = Teeth::name($n);
@endphp
<div class="card toothchart" data-chart="{{ json_encode(['save' => $chart['canEdit'] ? route('teeth.save', $p) : null, 'marks' => (object) $chart['marks'], 'history' => (object) $chart['history'], 'names' => $names, 'states' => Teeth::STATES, 'rx' => $chart['rxUrl'], 'rxShow' => auth()->user()->canOpen('prescriptions') ? url('/prescriptions') : null]) }}">
  <div class="row" style="justify-content:space-between;margin-bottom:10px">
    <h2 style="margin:0">Tooth chart</h2>
    <span class="chip">{{ ['adult' => 'Adult teeth (32)', 'child' => 'Milk teeth (20)', 'both' => 'Adult and milk teeth'][$chart['mode']] }}@if ($p->age !== null) · {{ $p->age }} yrs @else · age not entered @endif</span>
  </div>
  @foreach (['adult' => [Teeth::UPPER, Teeth::LOWER, 'Adult teeth (32)'], 'child' => [Teeth::UPPER_CHILD, Teeth::LOWER_CHILD, 'Milk teeth (20)']] as $set => [$upT, $loT, $title])
    @continue($chart['mode'] !== 'both' && $chart['mode'] !== $set)
    @if ($chart['mode'] === 'both')<h3 class="jawset">{{ $title }}</h3>@endif
    <div class="row muted jawlbl"><span>Patient's right</span><span>Upper jaw</span><span>Patient's left</span></div>
    <div class="teeth" data-teeth="{{ $set }}">{!! $jaw($upT) !!}{!! $jaw($loT) !!}</div>
    <div class="row muted jawlbl"><span></span><span>Lower jaw</span><span></span></div>
  @endforeach
  <div class="legend">@foreach (Teeth::STATES as $k => $l)<span><i class="st-{{ $k }}"></i>{{ $l }}</span>@endforeach<span><i class="hist-dot"></i>Has visits</span></div>
  <div class="tpanel" data-panel><p class="muted" style="margin:0">Tap a tooth to see its history{{ $chart['canEdit'] ? ' or mark what was found' : '' }}.</p></div>
  <details style="margin-top:12px"><summary style="cursor:pointer;font-weight:500">How the numbering works</summary><p class="muted" style="margin:8px 0 0;max-width:70ch">The chart uses the FDI two-digit system that Indian dental colleges teach. The first digit is the quarter of the mouth: 1 upper right, 2 upper left, 3 lower left, 4 lower right. The second digit counts from the front tooth (1) to the wisdom tooth (8). So 36 is the lower left first molar. Milk teeth use 5 to 8 for the quarters, so 55 is the upper right second milk molar.</p></details>
</div>
