@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Status')
@section('title', $p->name.' · SmileDesk')
@section('content')
<div class="card" style="margin-bottom:16px">
  <div class="row" style="justify-content:space-between;align-items:flex-start">
    <div class="who" style="gap:16px">
      @include('partials.avatar', ['name' => $p->name, 'size' => 64])
      <div>
        <h1 style="font-size:1.4rem">{{ $p->name }}</h1>
        <div class="row muted" style="gap:14px;margin-top:4px">
          <span class="mono">{{ $p->code }}</span>
          @if ($p->age !== null)<span>{{ $p->age }} yrs</span>@endif
          @if ($p->gender)<span>{{ $p->gender }}</span>@endif
          <span class="mono">{{ Fmt::showMobile($p->mobile) }}</span>
          @if ($p->relativeLabel())<span>{{ $p->relativeLabel() }}</span>@endif
          @if ($p->old_id)<span>Old ID {{ $p->old_id }}</span>@endif
        </div>
        <div class="row" style="margin-top:8px">
          @unless ($p->active)<span class="chip c-bad">Disabled</span>@endunless
          @if ($balance > 0)<span class="chip c-bad">Balance due {{ \App\Support\Money::inr($balance) }}</span>@endif
          @foreach ($p->medical_history ?? [] as $h)<span class="chip c-warn">{{ $h }}</span>@endforeach
        </div>
      </div>
    </div>
    <div class="row">
      @if (auth()->user()->canOpen('appointments') && $p->active)<a class="btn pri" href="{{ route('appointments.create', ['patient' => $p->id]) }}">Book appointment</a>@endif
      @if ($visits !== null && $p->active)<a class="btn pri" href="{{ route('rx.create', ['patient' => $p->id]) }}">New visit & Rx</a>@endif
      @if (auth()->user()->canOpen('billing'))<a class="btn" href="{{ route('billing.create', ['patient' => $p->id]) }}">New bill</a>@endif
      @if (auth()->user()->canEditPatients())<a class="btn" href="{{ route('patients.edit', $p) }}">Edit details</a>@endif
      @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('patients.destroy', $p) }}" data-confirm="Delete {{ $p->name }} with all their appointments and bills? This cannot be undone.">@csrf @method('DELETE')<button class="btn danger">Delete permanently</button></form>
      @endif
    </div>
  </div>
</div>
@if ($photos->isNotEmpty())
  @php($nRx = $photos->where('kind', 'RX')->count())
  @php($nX = $photos->where('kind', 'XRAY')->count())
  <nav class="jumpbar" aria-label="Jump to">
    <span class="muted">Files:</span>
    @if ($nRx)<a class="btn sm" href="#photos-RX">Prescription photos <b>{{ $nRx }}</b></a>@endif
    @if ($nX)<a class="btn sm" href="#photos-XRAY">X-rays <b>{{ $nX }}</b></a>@endif
  </nav>
@endif
@if ($chart)<div style="margin-bottom:16px">@include('teeth.chart', ['chart' => $chart])</div>@endif
<div class="grid g2">
  @if ($visits !== null)
  <div class="card">
    <h2>Visits & prescriptions</h2>
    @if ($visits->isNotEmpty())
      <div class="tw"><table class="mt"><thead><tr><th>Date</th><th>Visit</th><th></th></tr></thead><tbody>
        @foreach ($visits as $v)
          <tr><td style="white-space:nowrap">{{ Fmt::date($v->date) }}</td><td class="mt-main">{{ $v->procedure ?: $v->complaint ?: 'Visit' }}<br><small class="muted">{{ $v->teeth ? 'Tooth '.$v->teeth.' · ' : '' }}{{ $v->doctor?->name }}</small>@if ($v->rx_photos || $v->xrays)<br><span class="photo-chip">{{ implode(' · ', array_filter([$v->rx_photos ? $v->rx_photos.' Rx '.($v->rx_photos === 1 ? 'photo' : 'photos') : null, $v->xrays ? $v->xrays.' '.($v->xrays === 1 ? 'X-ray' : 'X-rays') : null])) }}</span>@endif</td><td class="mt-end" style="text-align:right"><a class="btn sm" href="{{ route('rx.show', $v) }}">View</a></td></tr>
        @endforeach
      </tbody></table></div>
    @else
      <p class="muted">No visits written yet.</p>
    @endif
  </div>
  @endif
  <div class="card">
    <h2>Appointments</h2>
    @if ($appts->isNotEmpty())
      <div class="tw"><table class="mt"><thead><tr><th>Date</th><th>Time</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
        @foreach ($appts as $a)
          <tr class="{{ $a->status === 'CANCELLED' ? 'off' : '' }}"><td>{{ Fmt::date($a->date) }}</td><td class="mono">{{ Fmt::time($a->start_min) }}</td><td class="mt-main">{{ $a->treatment ?? '—' }}<br><small class="muted">{{ $a->chair?->name }}{{ $a->doctor ? ' · '.$a->doctor->name : '' }}</small></td><td class="mt-end"><span class="chip {{ Status::cls($a->status) }}">{{ Status::label($a->status) }}</span></td></tr>
        @endforeach
      </tbody></table></div>
    @else
      <p class="muted">No appointments yet.</p>
    @endif
  </div>
  @if ($bills !== null)
  <div class="card">
    <div class="row" style="justify-content:space-between;align-items:baseline"><h2>Bills</h2>@if ($bills->isNotEmpty())<a class="btn sm" href="{{ route('billing.statement', $p) }}">Statement</a>@endif</div>
    @if ($bills->isNotEmpty())
      @php($live = $bills->whereNull('cancelled_at'))
      <div class="billsum"><span class="muted">Charged <b class="mono" style="color:var(--ink)">{{ \App\Support\Money::inr($live->sum('total')) }}</b></span><span class="muted">Paid <b class="mono" style="color:var(--ok)">{{ \App\Support\Money::inr($live->sum('paid')) }}</b></span><span class="muted">Balance <b class="mono" style="color:{{ $live->sum(fn ($b) => $b->due()) ? 'var(--bad)' : 'var(--ink)' }}">{{ \App\Support\Money::inr($live->sum(fn ($b) => $b->due())) }}</b></span></div>
      <div class="tw"><table class="mt"><thead><tr><th>Bill</th><th>Date</th><th style="text-align:right">Total</th><th>Status</th></tr></thead><tbody>
        @foreach ($bills as $b)
          @php([$bl, $bc] = \App\Models\Invoice::STATE[$b->state()])
          <tr class="{{ $b->cancelled_at ? 'off' : '' }}"><td><a class="mono" href="{{ route('billing.show', $b) }}">{{ $b->number }}</a></td><td>{{ Fmt::date($b->date) }}</td><td class="mono mt-amt" style="text-align:right">{{ \App\Support\Money::inr($b->total) }}</td><td class="mt-end"><span class="chip {{ $bc }}">{{ $bl }}{{ $b->state() === 'PART' ? ' · '.\App\Support\Money::inr($b->due()).' due' : '' }}</span></td></tr>
        @endforeach
      </tbody></table></div>
    @else
      <p class="muted">No bills yet.</p>
    @endif
  </div>
  @endif
  <div class="card">
    <h2>Details</h2>
    <p style="margin:0"><span class="muted">Address:</span> {{ $p->address ?: '—' }}</p>
    <p><span class="muted">Date of birth:</span> {{ $p->dob ? Fmt::date($p->dob) : '—' }}</p>
    <h2 style="margin-top:16px">Same mobile number</h2>
    @forelse ($family as $f)
      <div><a href="{{ route('patients.show', $f) }}">{{ $f->name }}</a> <span class="mono muted">{{ $f->code }}</span></div>
    @empty
      <p class="muted">No other patients share this number.</p>
    @endforelse
  </div>
</div>
@if ($photos->isNotEmpty())
<div class="card pgallery" id="photos" style="margin-top:16px">
  @foreach (\App\Models\VisitFile::KINDS as $k => $label)
    @php($list = $photos->where('kind', $k)->values())
    @continue($list->isEmpty())
    <section id="photos-{{ $k }}" class="pg-sec" data-pgallery="{{ json_encode($list->map(fn ($f) => \App\Http\Controllers\VisitFileController::json($f, auth()->user()))->values()) }}">
      <h2>{{ $label }} <small class="muted">{{ $list->count() }}</small></h2>
      @php($i = 0)
      @foreach ($list->groupBy('visit_id') as $vid => $group)
        <div class="pg-visit">
          <div class="row" style="justify-content:space-between"><b>Visit {{ Fmt::date($group->first()->visit_date) }}</b><a class="btn sm" href="{{ route('rx.show', $vid) }}">Open visit</a></div>
          <small class="muted">{{ $group->first()->visit_what ?: $group->first()->visit_cc }}</small>
          <div class="vp-grid" style="margin-top:8px">
            @foreach ($group as $f)
              <figure class="vp-fig"><div class="vp-tile"><button type="button" class="vp-img" data-i="{{ $i++ }}" aria-label="Open {{ $label }} {{ $loop->iteration }}"><img src="{{ route('visitfiles.show', [$f, 'thumb' => 1]) }}" alt="" loading="lazy"></button></div>
                <figcaption>{{ $f->created_at->format('j M Y, g:i A') }}@if ($f->uploader)<br>{{ $f->uploader->name }}@endif</figcaption></figure>
            @endforeach
          </div>
        </div>
      @endforeach
    </section>
  @endforeach
</div>
@endif
@endsection
