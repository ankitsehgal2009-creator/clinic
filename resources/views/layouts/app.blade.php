@extends('layouts.base')
@php($me = auth()->user())
@section('body')
<div class="shell">
  <aside class="side" aria-label="Main menu">
    <div class="brand">@if ($me->clinic->logo)<div class="logo img"><img src="{{ $me->clinic->logo }}" alt=""></div>@else<div class="logo">S</div>@endif<div><b>SmileDesk</b><span>{{ $me->clinic->name }}</span></div></div>
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
        <a class="btn sm" href="{{ route('appearance') }}" title="Choose your colours and style"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.6-.8 1.6-1.6 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.7-1.6 1.6-1.6H16a5 5 0 0 0 5-5C21 6.4 17 3 12 3Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7" r="1"/></svg>Appearance</a>
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
