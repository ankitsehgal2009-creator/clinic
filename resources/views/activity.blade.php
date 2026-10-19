@extends('layouts.app')
@section('title', 'Activity log · SmileDesk')
@section('content')
<div class="ph">
  <div><h1>Activity log</h1><p>Every action, who did it and when. Entries can't be edited.</p></div>
  <form class="row"><select name="user" class="inp" style="width:auto" aria-label="Person"><option value="">Everyone</option>@foreach ($people as $p)<option value="{{ $p->id }}" @selected($who == $p->id)>{{ $p->name }}</option>@endforeach</select><button class="btn">Show</button></form>
</div>
<div class="tw"><table>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead>
  <tbody>
  @forelse ($rows as $r)
    <tr>
      <td class="mono" style="white-space:nowrap">{{ $r->created_at->format('d M, h:i A') }}</td>
      <td><div class="who">@include('partials.avatar', ['name' => $r->user->name, 'photo' => $r->user->photo])<div><b>{{ $r->user->name }}</b><small>{{ $r->user->roleLabel() }}</small></div></div></td>
      <td @if (str_starts_with($r->action, 'Deleted')) style="color:var(--bad)" @endif>{{ $r->action }}</td>
      <td class="muted">{{ $r->details }}</td>
    </tr>
  @empty
    <tr><td colspan="4" class="muted" style="text-align:center;padding:30px">Nothing recorded yet.</td></tr>
  @endforelse
  </tbody>
</table></div>
@endsection
