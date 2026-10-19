@extends('layouts.app')
@use('App\Support\Fmt')
@use('App\Support\Pages')
@php($me = auth()->user())
@php($admin = $me->isAdmin())
@php($canToggle = $admin || $me->role === 'DOCTOR')
@section('title', 'Staff · SmileDesk')
@section('content')
<div class="ph"><div><h1>Staff</h1><p>The admin and doctors can turn staff on or off. A turned-off person can't sign in.</p></div></div>
<div class="tw" style="margin-bottom:20px"><table>
  <thead><tr><th>Person</th><th>Role</th><th>Username</th><th>Mobile</th><th>Active</th></tr></thead>
  <tbody>
  @foreach ($staff as $s)
    <tr class="{{ $s->active ? '' : 'off' }}">
      <td><div class="who">@include('partials.avatar', ['name' => $s->name, 'photo' => $s->photo])<div><b>{{ $s->name }}</b><small>{{ implode(' · ', array_filter([$s->qualification, $s->reg_no ? "Reg. $s->reg_no" : null])) }}</small></div></div></td>
      <td><span class="chip c-pri">{{ $s->roleLabel() }}</span>@if ($s->is_owner) <span class="chip c-acc">Owner</span>@endif</td>
      <td class="mono">{{ $s->username }}</td>
      <td class="mono">{{ Fmt::showMobile($s->mobile) }}</td>
      <td><button type="button" class="toggle {{ $s->active ? 'on' : '' }}" aria-pressed="{{ $s->active ? 'true' : 'false' }}" aria-label="Turn {{ $s->name }} on or off" data-toggle="{{ route('staff.active', $s) }}" @disabled(! $canToggle || $s->id === $me->id || ($s->isAdmin() && ! $admin))></button></td>
    </tr>
  @endforeach
  </tbody>
</table></div>
@if ($admin)
<div class="grid g2">
  <div class="card">
    <h2>Page access</h2>
    <p class="muted" style="margin-top:-6px">Choose which pages each doctor and staff member can open. The admin always sees every page.</p>
    @if ($others->isEmpty())
      <p class="muted">Add staff first.</p>
    @else
      <div class="tw"><table>
        <thead><tr><th>Page</th>@foreach ($others as $p)<th style="text-align:center;text-transform:none;letter-spacing:0">{{ $p->name }}<br><small style="font-weight:400">{{ $p->roleLabel() }}</small></th>@endforeach</tr></thead>
        <tbody>
        @foreach (Pages::PAGES as $key => $pg)
          <tr><td>{{ $pg['label'] }}</td>
            @foreach ($others as $p)
              @php($on = in_array($key, $p->pageKeys(), true))
              <td style="text-align:center"><button type="button" class="toggle {{ $on ? 'on' : '' }}" aria-pressed="{{ $on ? 'true' : 'false' }}" aria-label="{{ $pg['label'] }} for {{ $p->name }}" data-page="{{ $key }}" data-pages-url="{{ route('staff.pages', $p) }}" data-person="{{ $p->id }}"></button></td>
            @endforeach
          </tr>
        @endforeach
        </tbody>
      </table></div>
    @endif
  </div>
  <div class="card">
    <h2>Add staff</h2>
    <form method="post" action="{{ route('staff.store') }}" class="stack">
      @csrf
      <div class="row" style="align-items:flex-start">
        <div style="text-align:center"><button type="button" class="av" data-photo style="width:72px;height:72px;font-size:22px;border:1px dashed var(--line);cursor:pointer" aria-label="Upload photo">+</button><input type="hidden" name="photo"><div class="hint">Photo</div></div>
        <div class="form" style="flex:1">
          <div class="field"><label for="sn">Full name</label><input id="sn" name="name" class="inp" value="{{ old('name') }}" data-name-src required></div>
          <div class="field"><label for="su">Username (login)</label><input id="su" name="username" class="inp mono" autocapitalize="none" autocomplete="off" spellcheck="false" maxlength="30" value="{{ old('username') }}" placeholder="e.g. priya" data-username required><span class="hint">Letters and numbers, no spaces. The mobile number can be shared.</span></div>
          <div class="field"><label for="sm">Mobile</label><input id="sm" name="mobile" class="inp" inputmode="tel" value="{{ old('mobile') }}" required></div>
          <div class="field"><label for="sr">Role</label><select id="sr" name="role" class="inp" data-role>@foreach (['RECEPTIONIST' => 'Receptionist', 'DOCTOR' => 'Doctor', 'ASSISTANT' => 'Assistant'] as $k => $l)<option value="{{ $k }}" @selected(old('role') === $k)>{{ $l }}</option>@endforeach</select></div>
          <div class="field"><label for="sp">Password</label><input id="sp" name="password" type="password" class="inp" minlength="6" required></div>
          <div class="field" data-doc-only><label for="sq">Qualification</label><input id="sq" name="qualification" class="inp" value="{{ old('qualification') }}"></div>
          <div class="field" data-doc-only><label for="sg">Registration no.</label><input id="sg" name="reg_no" class="inp" value="{{ old('reg_no') }}"></div>
        </div>
      </div>
      <div><button class="btn pri">Add staff</button></div>
    </form>
  </div>
</div>
@endif
@endsection
