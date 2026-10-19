@extends('layouts.app')
@use('App\Models\User')
@section('title', 'My appearance · SmileDesk')
@section('content')
<div class="ph"><div><h1>My appearance</h1><p>Choose how SmileDesk looks for you. Only your screen changes; everyone else keeps their own choice. Changes save by themselves.</p></div></div>
<form class="stack" data-appearance data-save="{{ route('appearance') }}">
  <section class="card">
    <h2>Colour theme</h2>
    <div class="swatches" role="radiogroup" aria-label="Colour theme">
      @foreach (User::THEMES as $k => $l)
        <label class="swatch" data-sw="{{ $k }}"><input type="radio" name="theme" value="{{ $k }}" @checked($me->pref('theme') === $k)><span class="sw-dot"></span><span>{{ $l }}</span></label>
      @endforeach
    </div>
  </section>
  <div class="grid g2">
    @foreach (['mode' => ['Light or dark', User::MODES], 'menu' => ['Menu style', User::MENUS], 'size' => ['Text size', User::SIZES], 'corners' => ['Corners', User::CORNERS]] as $name => [$title, $opts])
      <section class="card">
        <h2>{{ $title }}</h2>
        <div class="seg" role="radiogroup" aria-label="{{ $title }}">
          @foreach ($opts as $k => $l)
            <label><input type="radio" name="{{ $name }}" value="{{ $k }}" @checked($me->pref($name) === $k)><span>{{ $l }}</span></label>
          @endforeach
        </div>
      </section>
    @endforeach
  </div>
  <section class="card">
    <h2>Preview</h2>
    <div class="row">
      <button type="button" class="btn pri">Main button</button><button type="button" class="btn">Other button</button>
      <span class="chip c-pri">Confirmed</span><span class="chip c-ok">Paid</span><span class="chip c-warn">Waiting</span>
      <button type="button" class="toggle on" aria-pressed="true" aria-label="Sample switch" data-demo-toggle></button>
    </div>
  </section>
</form>
@endsection
