@extends('layouts.base')
@section('title', 'Clinic setup · SmileDesk')
@section('body')
<div class="content" style="max-width:900px;margin:0 auto">
  <div class="ph"><div><h1>One-time setup</h1><p>Fill this once for your clinic. You can change it later in Settings. <a href="{{ route('login') }}">Already set up? Sign in</a></p></div></div>
  @include('partials.flash')
  <form method="post" action="/setup" class="stack">
    @csrf
    <section class="card">
      <h2>1. Clinic</h2>
      <div class="form">
        <div class="field full"><label for="cn">Clinic name</label><input id="cn" name="clinic[name]" class="inp" value="{{ old('clinic.name') }}" required></div>
        <div class="field full"><label for="ca">Address</label><input id="ca" name="clinic[address]" class="inp" value="{{ old('clinic.address') }}" required></div>
        <div class="field"><label for="cc">City and PIN</label><input id="cc" name="clinic[city]" class="inp" value="{{ old('clinic.city') }}" required></div>
        <div class="field"><label for="cp">Clinic phone</label><input id="cp" name="clinic[phone]" class="inp" value="{{ old('clinic.phone') }}" required></div>
        <div class="field"><label for="cg">GSTIN (optional)</label><input id="cg" name="clinic[gstin]" class="inp mono" maxlength="15" value="{{ old('clinic.gstin') }}"></div>
        <div class="field"><label for="cx">Patient ID prefix</label><input id="cx" name="clinic[prefix]" class="inp mono" maxlength="6" value="{{ old('clinic.prefix', 'P-') }}" required><span class="hint">IDs will look like P-0001</span></div>
        <div class="field"><label for="ch">Number of chairs</label><input id="ch" name="clinic[chairs]" type="number" min="1" max="20" class="inp" value="{{ old('clinic.chairs', 2) }}" required></div>
        <div class="field full">
          <span class="lbl">Clinic logo (optional)</span>
          <div class="logo-up" data-logo>
            <button type="button" class="logo-box" data-logo-pick aria-label="Upload clinic logo">@if (old('clinic.logo'))<img src="{{ old('clinic.logo') }}" alt="Clinic logo">@else<span>+ Logo</span>@endif</button>
            <input type="hidden" name="clinic[logo]" value="{{ old('clinic.logo') }}" data-logo-value>
            <div class="hint">PNG or JPG. Printed on bills and shown in the menu.<br><button type="button" class="linkbtn {{ old('clinic.logo') ? '' : 'hide' }}" data-logo-clear>Remove logo</button></div>
          </div>
        </div>
      </div>
    </section>
    <section class="card">
      <h2>2. Admin (clinic owner or manager)</h2>
      <p class="hint" style="margin-top:-6px">The admin can do everything, including deleting records and choosing which pages each person sees.</p>
      @include('partials.person', ['p' => 'admin'])
    </section>
    <section class="card">
      <h2>3. Doctor</h2>
      <p class="hint" style="margin-top:-6px">This is the owner doctor, who can also change clinic settings and the logo. The admin and doctor can use the same mobile number; each just needs their own username.</p>
      @include('partials.person', ['p' => 'doctor'])
      <div class="form" style="margin-top:14px">
        <div class="field"><label for="dq">Qualification (optional)</label><input id="dq" name="doctor[qualification]" class="inp" placeholder="BDS, MDS" value="{{ old('doctor.qualification') }}"></div>
        <div class="field"><label for="dr">Dental council registration no. (optional)</label><input id="dr" name="doctor[reg_no]" class="inp mono" value="{{ old('doctor.reg_no') }}"></div>
        <div class="field"><label for="ds">Specialisation</label><select id="ds" name="doctor[specialisation]" class="inp">@foreach (['General Dentist', 'Endodontist', 'Orthodontist', 'Periodontist', 'Prosthodontist', 'Oral Surgeon', 'Pedodontist'] as $s)<option @selected(old('doctor.specialisation') === $s)>{{ $s }}</option>@endforeach</select></div>
        <div class="field"><label for="de">Email</label><input id="de" name="doctor[email]" type="email" class="inp" value="{{ old('doctor.email') }}"></div>
      </div>
    </section>
    <section class="card">
      <h2>4. Receptionists</h2>
      <div class="stack" data-recs>
        @foreach (range(0, max(0, count(old('receptionists', [[]])) - 1)) as $i)
          <div class="rec" style="border:1px solid var(--line);border-radius:12px;padding:12px">@include('partials.person', ['p' => "receptionists[$i]", 'required' => false])</div>
        @endforeach
      </div>
      <button type="button" class="btn" style="margin-top:12px" data-add-rec>+ Add another receptionist</button>
    </section>
    <div><button class="btn pri">Finish setup</button></div>
  </form>
  @include('partials.credit')
</div>
@endsection
