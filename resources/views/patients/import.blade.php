@extends('layouts.app')
@section('title', 'Import old patients · SmileDesk')
@section('content')
<div class="ph"><div><h1>Import old patients</h1><p>Bring in patients from your old software or an Excel sheet. Each one gets a new SmileDesk ID, and their old ID is kept.</p></div></div>
<div id="importer" data-read="{{ route('import.read') }}" data-store="{{ route('import.store') }}" data-patients="{{ route('patients.index') }}">
  <div class="grid g2" data-step="pick">
    <label class="card stack drop" data-drop>
      <b style="font-size:1.05rem">Drop your old patients file here</b>
      <span class="muted">or tap to choose · CSV or Excel (.xlsx)</span>
      <input type="file" accept=".csv,.xlsx,.xls,text/csv" hidden data-file>
      <div class="err hide" data-err></div>
    </label>
    <div class="card">
      <h2>What the file should have</h2>
      <p class="muted">One row per patient with a header row on top. Column names don't need to match; you choose what each column means next. Full name and mobile number are required.</p>
      <p class="hint">Exports from Kivi, Practo, Excel or Google Sheets work. +91, spaces and a leading 0 in mobile numbers are cleaned up. Dates like 12/04/1979 are read as day/month/year.</p>
      <div class="tw" style="margin:12px 0"><table class="sample">
        <thead><tr><th>Old patient ID</th><th>Full name</th><th>Mobile number</th><th>Gender</th><th>Date of birth</th><th>Father / husband name</th></tr></thead>
        <tbody>
          <tr><td class="mono">1021</td><td>Rohan Deshpande</td><td class="mono">9820145567</td><td>Male</td><td class="mono">12/03/1992</td><td>Suresh Deshpande</td></tr>
          <tr><td class="mono">1022</td><td>Meera Iyer</td><td class="mono">+91 99300 21456</td><td>Female</td><td class="muted">blank</td><td>Karthik Iyer</td></tr>
        </tbody>
      </table></div>
      <p class="hint" style="margin-top:0">The sample also has Age, Address and Medical history columns. Separate medical history items with ; like Diabetes; BP.</p>
      <a class="btn pri" href="{{ route('import.sample') }}" download>Download sample file (CSV)</a>
    </div>
  </div>
  <div class="grid g2 hide" data-step="map"></div>
  <div class="card stack hide" data-step="done"></div>
</div>
@endsection
