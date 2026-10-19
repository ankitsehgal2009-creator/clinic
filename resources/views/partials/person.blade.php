{{-- Name, username, mobile, password and photo for one person. $p = field prefix, e.g. admin or receptionists[0] --}}
@php($dot = str_replace(['[', ']'], ['.', ''], $p))
<div class="row" style="align-items:flex-start;gap:16px">
  <div style="text-align:center">
    <button type="button" class="av" data-photo style="width:72px;height:72px;font-size:22px;border:1px dashed var(--line);cursor:pointer" aria-label="Upload photo">@if(old("$dot.photo"))<img src="{{ old("$dot.photo") }}" alt="">@else+@endif</button>
    <input type="hidden" name="{{ $p }}[photo]" value="{{ old("$dot.photo") }}">
    <div class="hint">Photo</div>
  </div>
  <div class="form" style="flex:1;min-width:240px">
    <div class="field"><label>Full name</label><input name="{{ $p }}[name]" class="inp" value="{{ old("$dot.name") }}" data-name-src {{ $required ?? true ? 'required' : '' }}></div>
    <div class="field"><label>Username (used to log in)</label><input name="{{ $p }}[username]" class="inp mono" autocapitalize="none" autocomplete="off" spellcheck="false" maxlength="30" value="{{ old("$dot.username") }}" placeholder="e.g. priya" data-username {{ $required ?? true ? 'required' : '' }}></div>
    <div class="field"><label>Mobile</label><input name="{{ $p }}[mobile]" class="inp" inputmode="tel" value="{{ old("$dot.mobile") }}" {{ $required ?? true ? 'required' : '' }}></div>
    <div class="field"><label>Password</label><input name="{{ $p }}[password]" type="password" class="inp" minlength="6" {{ $required ?? true ? 'required' : '' }}></div>
  </div>
</div>
