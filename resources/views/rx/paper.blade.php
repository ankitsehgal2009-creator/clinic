{{-- One printed prescription. $c clinic, $d design, $doc doctor (or null), $pt patient, $v visit fields, $meds list of medicine arrays --}}
@use('App\Support\Rx')
@php
  $ageSex = trim(($pt->age !== null ? $pt->age : '').($pt->gender ? ' / '.substr($pt->gender, 0, 1) : ''), ' /');
  $vdate = date('j M Y', strtotime($v['date']));
  $next = $v['next_visit'] ? date('D, j M Y', strtotime($v['next_visit'])) : null;
  $done = trim(implode(' · ', array_filter([$v['teeth'] ? 'Tooth '.$v['teeth'] : null, $v['procedure']])));
  $logo = $c->logo ? '<img class="rx-logo" src="'.e($c->logo).'" alt="">' : '';
@endphp
@if ($d === 'modern')
<div class="paper rx rx-modern">
  <div class="rm-band"><div>{!! $logo !!}<h3>{{ $c->name }}</h3><div class="sm">{{ $c->address }}, {{ $c->city }} · {{ $c->phone }}</div></div>
    @if ($doc)<div class="rm-doc"><b>{{ $doc->name }}</b>@if ($doc->qualification)<div class="sm">{{ $doc->qualification }}</div>@endif @if ($doc->reg_no)<div class="sm">Reg. No. {{ $doc->reg_no }}</div>@endif</div>@endif</div>
  <div class="rm-pt"><div><span>Patient</span><b>{{ $pt->name }}</b></div><div><span>Age / Sex</span><b>{{ $ageSex ?: '—' }}</b></div><div><span>ID</span><b>{{ $pt->code }}</b></div><div><span>Date</span><b>{{ $vdate }}</b></div></div>
  @if ($v['complaint'])<p class="sm"><b>Complaint:</b> {{ $v['complaint'] }}</p>@endif
  @if ($v['diagnosis'] || $done)<p class="sm"><b>Diagnosis:</b> {{ implode(' · ', array_filter([$v['diagnosis'], $done])) }}</p>@endif
  <div class="rm-rx">℞</div>
  @include('rx.meds', ['meds' => $meds, 'num' => false])
  @if ($v['advice'])<p class="sm"><b>Advice:</b> {!! nl2br(e($v['advice'])) !!}</p>@endif
  <div class="rx-foot rm-foot">@if ($next)<span class="rm-next">Next visit · {{ $next }}</span>@else<span></span>@endif<div class="sign"><div>{{ $doc?->name ?? 'Doctor' }}</div></div></div>
<div class="print-credit">Designed &amp; Developed By Er. Ankit Sehgal</div>
</div>
@elseif ($d === 'minimal')
<div class="paper rx rx-min">
  <div class="rx-top"><div>{!! $logo !!}<div class="mn-c">{{ $c->name }}</div>@if ($doc)<h3>{{ $doc->name }}</h3><div class="sm">{{ implode(' · ', array_filter([$doc->qualification, $doc->reg_no ? 'Reg. No. '.$doc->reg_no : null])) }}</div>@endif</div><div class="sm" style="text-align:right">{{ $vdate }}<br>{{ $pt->code }}</div></div>
  <p style="margin:22px 0 4px">{{ $pt->name }}{{ $ageSex ? ', '.str_replace(' / ', ', ', $ageSex) : '' }}</p>
  @if ($v['diagnosis'] || $done)<p class="sm" style="margin:0">{{ implode(' · ', array_filter([$v['diagnosis'], $done])) }}</p>@endif
  <div class="mn-rx">℞</div>
  @forelse ($meds as $i => $m)
    <div class="mn-m"><span class="mn-n">{{ $i + 1 }}</span><div><b>{{ Rx::prefix($m['form'] ?? null) }}{{ $m['name'] }}</b><div class="sm">{{ implode(' · ', array_filter([$m['dose'] ?? null, $m['timing'] ?? null, $m['days'] ?? null])) }}</div></div></div>
  @empty
    <p class="sm">No medicines.</p>
  @endforelse
  @if ($v['advice'])<p class="sm" style="margin-top:16px">{!! nl2br(e($v['advice'])) !!}</p>@endif
  @if ($next)<p class="sm">Next visit {{ $next }}</p>@endif
  <div class="sign"><div>{{ $doc?->name ?? 'Doctor' }}</div></div>
  <div class="sm mn-f">{{ $c->address }}, {{ $c->city }} · {{ $c->phone }}</div>
<div class="print-credit">Designed &amp; Developed By Er. Ankit Sehgal</div>
</div>
@elseif ($d === 'bilingual')
<div class="paper rx rx-trad"><div class="tr-in">
  <div class="tr-h">@if ($c->logo){!! $logo !!}@else<div class="tr-logo">🦷</div>@endif<h3>{{ $c->name }}</h3><div class="sm">{{ $c->address }}, {{ $c->city }} · फ़ोन {{ $c->phone }}</div>
    @if ($doc)<div class="tr-doc"><b>{{ $doc->name }}</b>{{ $doc->qualification ? ' · '.$doc->qualification : '' }}{{ $doc->reg_no ? ' · Reg. No. '.$doc->reg_no : '' }}</div>@endif</div>
  <div class="tr-pt"><span>नाम / Name: <b>{{ $pt->name }}</b></span>@if ($pt->age !== null)<span>आयु / Age: <b>{{ $pt->age }}</b></span>@endif @if ($pt->gender)<span>लिंग / Sex: <b>{{ substr($pt->gender, 0, 1) }}</b></span>@endif<span>दिनांक / Date: <b>{{ date('d-m-Y', strtotime($v['date'])) }}</b></span><span>ID: <b>{{ $pt->code }}</b></span></div>
  @if ($v['diagnosis'] || $done)<p class="sm"><b>निदान / Diagnosis:</b> {{ implode(' · ', array_filter([$v['diagnosis'], $done])) }}</p>@endif
  <div class="rxs">℞</div>
  <table><thead><tr><th>#</th><th>दवा / Medicine</th><th>खुराक / Dose</th><th>कब / When</th><th>दिन / Days</th></tr></thead><tbody>
    @forelse ($meds as $i => $m)<tr><td>{{ $i + 1 }}</td><td><b>{{ Rx::prefix($m['form'] ?? null) }}{{ $m['name'] }}</b></td><td class="mono">{{ $m['dose'] ?? '' }}</td><td>{{ $m['timing'] ?? '' }}</td><td>{{ $m['days'] ?? '' }}</td></tr>
    @empty<tr><td colspan="5" class="sm">No medicines.</td></tr>@endforelse
  </tbody></table>
  @if ($v['advice'])<p class="sm"><b>सलाह / Advice:</b> {!! nl2br(e($v['advice'])) !!}</p>@endif
  @if ($next)<p class="sm"><b>अगली विज़िट / Next visit:</b> {{ $next }}</p>@endif
  <div class="rx-foot"><div class="tr-stamp">Clinic stamp</div><div class="sign"><div>हस्ताक्षर / Signature</div></div></div>
<div class="print-credit">Designed &amp; Developed By Er. Ankit Sehgal</div>
</div></div>
@else
<div class="paper rx">
  <div class="lh"><div>{!! $logo !!}<h3>{{ $c->name }}</h3><div class="sm">{{ $c->address }}, {{ $c->city }}<br>Ph {{ $c->phone }}</div></div>
    @if ($doc)<div style="text-align:right"><b>{{ $doc->name }}</b><div class="sm">{{ $doc->qualification }}@if ($doc->reg_no)<br>Reg. No. {{ $doc->reg_no }}@endif</div></div>@endif</div>
  <div class="rx-top sm"><span><b class="ink">{{ $pt->name }}</b>{{ $ageSex ? ' · '.$ageSex : '' }} · {{ $pt->code }}</span><span>{{ $vdate }}</span></div>
  @if ($v['complaint'] || $done)<p class="sm" style="margin:8px 0">{{ implode(' · ', array_filter([$v['complaint'] ? 'C/o: '.$v['complaint'] : null, $done ? 'Done: '.$done : null])) }}</p>@endif
  @if ($v['diagnosis'])<p class="sm" style="margin:0 0 8px">Diagnosis: {{ $v['diagnosis'] }}</p>@endif
  <div class="rxs">℞</div>
  @include('rx.meds', ['meds' => $meds, 'num' => true])
  @if ($v['advice'])<p class="sm" style="margin-top:12px"><b class="ink">Advice:</b> {!! nl2br(e($v['advice'])) !!}</p>@endif
  @if ($next)<p class="sm"><b class="ink">Next visit:</b> {{ $next }}</p>@endif
  <div class="sign"><div>{{ $doc?->name ?? 'Doctor' }}</div></div>
<div class="print-credit">Designed &amp; Developed By Er. Ankit Sehgal</div>
</div>
@endif
