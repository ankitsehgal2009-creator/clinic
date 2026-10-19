@extends('layouts.base')
@section('title', 'Login · SmileDesk')
@section('body')
<div class="auth">
  <div class="art">
    <div class="brand" style="padding:0"><div class="logo">S</div><div><b>SmileDesk</b><span>Clinic software for Indian dental practices</span></div></div>
    <h1>Every chair, patient and rupee in one place.</h1>
    <p>Each person signs in with their own mobile number. What they can see depends on their role.</p>
  </div>
  <div class="frm">
    <form class="box" method="post" action="/login">
      @csrf
      <h2 style="font-size:1.5rem">Login</h2>
      @include('partials.flash')
      <div class="field"><label for="mobile">Mobile number</label><input id="mobile" name="mobile" class="inp" inputmode="tel" autocomplete="username" value="{{ old('mobile') }}" required autofocus></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" class="inp" autocomplete="current-password" required></div>
      <button class="btn pri" style="justify-content:center;padding:10px">Sign in</button>
      <p class="hint" style="margin:0">New clinic? <a href="{{ route('setup') }}">Set up your clinic</a></p>
    </form>
  </div>
</div>
@endsection
