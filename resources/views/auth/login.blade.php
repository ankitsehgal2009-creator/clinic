@extends('layouts.base')
@section('title', 'Login · SmileDesk')
@section('bodyclass', 'login-body')
@section('body')
<div class="lg">
  <div class="lg-art" aria-hidden="true">
    <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
    <svg class="tooth t1" viewBox="0 0 64 64"><path d="M20 6c-8 0-13 6-13 15 0 8 4 13 6 22 2 9 4 15 8 15s5-7 6-12c1-4 3-6 5-6s4 2 5 6c1 5 2 12 6 12s6-6 8-15c2-9 6-14 6-22 0-9-5-15-13-15-5 0-8 3-12 3S25 6 20 6Z"/></svg>
    <svg class="tooth t2" viewBox="0 0 64 64"><path d="M20 6c-8 0-13 6-13 15 0 8 4 13 6 22 2 9 4 15 8 15s5-7 6-12c1-4 3-6 5-6s4 2 5 6c1 5 2 12 6 12s6-6 8-15c2-9 6-14 6-22 0-9-5-15-13-15-5 0-8 3-12 3S25 6 20 6Z"/></svg>
    <svg class="tooth t3" viewBox="0 0 64 64"><path d="M20 6c-8 0-13 6-13 15 0 8 4 13 6 22 2 9 4 15 8 15s5-7 6-12c1-4 3-6 5-6s4 2 5 6c1 5 2 12 6 12s6-6 8-15c2-9 6-14 6-22 0-9-5-15-13-15-5 0-8 3-12 3S25 6 20 6Z"/></svg>
    <div class="lg-copy">
      <div class="lg-brand"><div class="logo">S</div><b>SmileDesk</b></div>
      <h1>Every chair, patient and rupee <span class="lg-grad">in one place.</span></h1>
      <p class="lg-rot">Made for Indian dental clinics: <span data-rotate="Appointments by chair|Bills with GST|Daily cash closing|Patient records|Import from old software">Appointments by chair</span></p>
      <ul class="lg-feats">
        <li style="--d:.5s"><span>15-min</span>slots per chair</li>
        <li style="--d:.65s"><span>CGST + SGST</span>bills done for you</li>
        <li style="--d:.8s"><span>Role-wise</span>access for staff</li>
      </ul>
    </div>
  </div>
  <div class="lg-side">
    <form class="lg-card {{ session('error') || $errors->any() ? 'shake' : '' }}" method="post" action="/login" data-login>
      @csrf
      <div class="lg-in" style="--d:.05s"><h2>Welcome back</h2><p class="muted" style="margin:4px 0 0">Sign in to your clinic</p></div>
      <div class="lg-in" style="--d:.1s">@include('partials.flash')</div>
      <div class="fl lg-in" style="--d:.15s">
        <input id="login" name="login" class="inp" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old('login') }}" placeholder=" " required autofocus>
        <label for="login">Username or mobile number</label>
        <svg class="fl-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
      </div>
      <div class="fl lg-in" style="--d:.2s">
        <input id="password" name="password" type="password" class="inp" autocomplete="current-password" placeholder=" " required>
        <label for="password">Password</label>
        <svg class="fl-ic" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
        <button type="button" class="fl-eye" data-eye aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
      </div>
      <button class="btn pri lg-btn lg-in" style="--d:.25s" data-login-btn><span>Sign in</span><i class="spin" aria-hidden="true"></i></button>
      <p class="hint lg-in" style="--d:.3s;margin:0;text-align:center">New clinic? <a href="{{ route('setup') }}">Set up your clinic</a></p>
    </form>
    @include('partials.credit', ['cls' => 'lg-in'])
  </div>
</div>
@endsection
