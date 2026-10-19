@extends('layouts.base')
@php($me = auth()->user())
@section('body')
<div class="shell">
  <aside class="side" aria-label="Main menu">
    <div class="brand"><div class="logo">S</div><div><b>SmileDesk</b><span>{{ $me->clinic->name }}</span></div></div>
    <nav>
      @php($cur = (string) request()->route()?->getName())
      @php($active = collect(\App\Support\Pages::PAGES)->keys()->first(fn ($k) => explode('.', \App\Support\Pages::PAGES[$k]['route'])[0] === explode('.', $cur)[0]))
      @foreach (\App\Support\Pages::PAGES as $key => $p)
        @if ($me->canOpen($key))
          <a href="{{ route($p['route']) }}" class="nitem {{ $active === $key ? 'on' : '' }}">{{ $p['label'] }}</a>
        @endif
      @endforeach
      <div class="ngroup">Coming soon</div>
      @foreach (\App\Support\Pages::COMING_SOON as $s)
        <span class="nitem soon" aria-disabled="true">{{ $s }}<span class="soonpill">Soon</span></span>
      @endforeach
    </nav>
  </aside>
  <div class="main">
    <div class="top">
      <span class="muted">{{ $me->clinic->name }} · {{ $me->clinic->city }}</span>
      <div class="row">
        <div class="who">@include('partials.avatar', ['name' => $me->name, 'photo' => $me->photo])<div><b style="font-size:13px">{{ $me->name }}</b><small>{{ $me->roleLabel() }}</small></div></div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="btn sm">Log out</button></form>
      </div>
    </div>
    <main class="content">
      @include('partials.flash')
      @yield('content')
    </main>
  </div>
</div>
@endsection
