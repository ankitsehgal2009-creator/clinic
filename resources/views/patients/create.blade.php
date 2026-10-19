@extends('layouts.app')
@section('title', 'Add patient · SmileDesk')
@section('content')
<div class="ph"><div><h1>Add patient</h1><p>The patient ID is given automatically: next is <b class="mono">{{ $nextCode }}</b></p></div></div>
<form method="post" action="{{ route('patients.store') }}" class="card">
  @csrf
  <div class="form">
    <div class="field"><label for="n">Full name</label><input id="n" name="name" class="inp" value="{{ old('name') }}" required></div>
    <div class="field"><label for="m">Mobile number</label><input id="m" name="mobile" class="inp" inputmode="tel" value="{{ old('mobile') }}" required></div>
    <div class="field"><label for="g">Gender</label><select id="g" name="gender" class="inp">@foreach (['Female', 'Male', 'Other'] as $g)<option @selected(old('gender') === $g)>{{ $g }}</option>@endforeach</select></div>
    <div class="field"><span class="lbl">Date of birth</span><div data-datepicker data-birth data-name="dob" data-value="{{ old('dob') }}" data-placeholder="Pick date of birth"></div></div>
    <div class="field" data-age-field><label for="a">Or age in years</label><input id="a" name="age" type="number" min="0" max="120" class="inp" value="{{ old('age') }}"></div>
    <div class="field"><label for="r">Relation</label><select id="r" name="relation_type" class="inp">@foreach (\App\Support\Status::REL as $k => $l)<option value="{{ $k }}" @selected(old('relation_type', 'HUSBAND') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label for="rn">Relative's name</label><input id="rn" name="relative_name" class="inp" value="{{ old('relative_name') }}"></div>
    <div class="field full"><label for="ad">Address</label><input id="ad" name="address" class="inp" value="{{ old('address') }}"></div>
    <div class="field full">
      <span class="lbl">Medical history</span>
      <div class="row" data-conds>
        @foreach (array_unique(array_merge(['Diabetes', 'High BP', 'Heart condition', 'Asthma', 'Thyroid', 'Pregnant', 'On blood thinners', 'Allergic to penicillin'], old('history', []))) as $c)
          <label class="chk"><input type="checkbox" name="history[]" value="{{ $c }}" @checked(in_array($c, old('history', [])))> {{ $c }}</label>
        @endforeach
      </div>
      <div class="row" style="margin-top:8px">
        <input class="inp" style="flex:1;max-width:320px" placeholder="Add another condition, e.g. Epilepsy" aria-label="New condition" data-cond-input>
        <button type="button" class="btn sm" data-cond-add>+ Add</button>
      </div>
    </div>
  </div>
  <div class="row" style="margin-top:18px">
    <button class="btn pri">Save patient</button>
    @if (auth()->user()->canOpen('appointments'))<button class="btn" name="then" value="book">Save and book appointment</button>@endif
  </div>
</form>
@endsection
