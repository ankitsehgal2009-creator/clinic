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
      @if (auth()->user()->canOpen('billing'))<a class="btn" href="{{ route('billing.create', ['patient' => $p->id]) }}">New bill</a>@endif
      @if (auth()->user()->isAdmin())
        <form method="post" action="{{ route('patients.destroy', $p) }}" data-confirm="Delete {{ $p->name }} with all their appointments and bills? This cannot be undone.">@csrf @method('DELETE')<button class="btn danger">Delete permanently</button></form>
      @endif
    </div>
  </div>
</div>
<div class="grid g2">
  <div class="card">
    <h2>Appointments</h2>
    @if ($appts->isNotEmpty())
      <div class="tw"><table><thead><tr><th>Date</th><th>Time</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
        @foreach ($appts as $a)
          <tr class="{{ $a->status === 'CANCELLED' ? 'off' : '' }}"><td>{{ Fmt::date($a->date) }}</td><td class="mono">{{ Fmt::time($a->start_min) }}</td><td>{{ $a->treatment ?? '—' }}<br><small class="muted">{{ $a->chair?->name }}{{ $a->doctor ? ' · '.$a->doctor->name : '' }}</small></td><td><span class="chip {{ Status::cls($a->status) }}">{{ Status::label($a->status) }}</span></td></tr>
        @endforeach
      </tbody></table></div>
    @else
      <p class="muted">No appointments yet.</p>
    @endif
  </div>
  @if ($bills !== null)
  <div class="card">
    <h2>Bills</h2>
    @if ($bills->isNotEmpty())
      <div class="tw"><table><thead><tr><th>Bill</th><th>Date</th><th style="text-align:right">Total</th><th>Status</th></tr></thead><tbody>
        @foreach ($bills as $b)
          @php([$bl, $bc] = \App\Models\Invoice::STATE[$b->state()])
          <tr class="{{ $b->cancelled_at ? 'off' : '' }}"><td><a class="mono" href="{{ route('billing.show', $b) }}">{{ $b->number }}</a></td><td>{{ Fmt::date($b->date) }}</td><td class="mono" style="text-align:right">{{ \App\Support\Money::inr($b->total) }}</td><td><span class="chip {{ $bc }}">{{ $bl }}{{ $b->state() === 'PART' ? ' · '.\App\Support\Money::inr($b->due()).' due' : '' }}</span></td></tr>
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
@endsection
