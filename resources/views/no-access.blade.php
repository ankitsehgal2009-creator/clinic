@extends('layouts.app')
@section('title', 'No access · SmileDesk')
@section('content')
<div style="max-width:520px;margin:60px auto;text-align:center">
  <h1>You can't open this page</h1>
  <p class="muted">Ask the clinic admin to turn it on for you under Staff.</p>
  @if (auth()->user()->canOpen('dashboard'))<a class="btn" href="{{ route('dashboard') }}">Go to dashboard</a>@endif
</div>
@endsection
