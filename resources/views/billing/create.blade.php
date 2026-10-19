@extends('layouts.app')
@use('App\Support\Money')
@section('title', 'New bill · SmileDesk')
@section('content')
<div class="ph"><div><h1>New bill</h1><p>Pick the patient, add treatments from the price list or type your own, and record any payment received now.</p></div><a class="btn" href="{{ route('billing.index') }}">Back to bills</a></div>
<form id="bill" method="post" action="{{ route('billing.store') }}" class="stack" data-gst="{{ $gst ? 1 : 0 }}"
  data-prices="{{ json_encode($priceData) }}" data-old="{{ json_encode(old('items', [])) }}">
  @csrf
  <input type="hidden" name="patient_id" value="{{ old('patient_id', $patient?->id) }}" data-pid>
  <div class="card form">
    <div class="field">
      <label for="pq">Patient</label>
      <input id="pq" class="inp" autocomplete="off" placeholder="Type at least 3 letters of the name" value="{{ old('patient_name', $patient?->name) }}" name="patient_name" role="combobox" aria-expanded="false" aria-controls="pq-list" data-search="/api/billing/patients">
      <div id="pq-list" class="sugg hide" role="listbox"></div>
      <span class="chip c-ok {{ $patient ? '' : 'hide' }}" style="align-self:flex-start" data-picked>{{ $patient ? $patient->code.' · '.$patient->mobile : '' }}</span>
      <span class="hint {{ $patient ? 'hide' : '' }}" data-hint>Suggestions come from registered patients.</span>
    </div>
    <div class="field"><span class="lbl">Bill date</span><div data-datepicker data-plain data-name="date" data-value="{{ old('date', $today) }}" data-max="{{ $today }}"></div></div>
  </div>

  <div class="card">
    <h2>Treatments</h2>
    <datalist id="price-names">@foreach ($prices as $p)<option value="{{ $p->name }}">{{ Money::inr($p->price) }}{{ $p->gst_rate ? ' + '.$p->gst_rate.'% GST' : '' }}</option>@endforeach</datalist>
    <div class="tw"><table class="lines"><thead><tr><th>Treatment</th><th>Tooth</th><th>Qty</th><th>Price ₹</th><th>Discount ₹</th>@if ($gst)<th>GST</th>@endif<th style="text-align:right">Amount</th><th></th></tr></thead>
      <tbody data-lines></tbody></table></div>
    <template id="line-tpl"><tr>
      <td style="min-width:200px"><input class="inp" data-f="name" list="price-names" placeholder="Pick or type" aria-label="Treatment" required><input type="hidden" data-f="sac"></td>
      <td style="width:90px"><input class="inp" data-f="tooth" placeholder="e.g. 36" aria-label="Tooth"></td>
      <td style="width:70px"><input class="inp" data-f="qty" type="number" min="1" max="99" value="1" aria-label="Quantity"></td>
      <td style="width:110px"><input class="inp mono" data-f="rate" inputmode="decimal" aria-label="Price"></td>
      <td style="width:110px"><input class="inp mono" data-f="discount" inputmode="decimal" placeholder="0" aria-label="Discount"></td>
      @if ($gst)<td style="width:105px"><select class="inp" data-f="gst" aria-label="GST rate">@foreach (Money::GST_RATES as $g)<option value="{{ $g }}">{{ $g ? $g.'%' : 'Exempt' }}</option>@endforeach</select></td>@endif
      <td class="mono" style="text-align:right;white-space:nowrap" data-amt>₹0</td>
      <td><button type="button" class="btn sm" data-del aria-label="Remove line">✕</button></td>
    </tr></template>
    <div class="row" style="justify-content:space-between;margin-top:12px;align-items:flex-start">
      <button type="button" class="btn" data-add>+ Add treatment</button>
      <table class="totals" style="width:auto;min-width:260px">
        <tr><td>Before discount</td><td class="mono" data-t="gross">₹0</td></tr>
        <tr><td>Discount</td><td class="mono" data-t="discount">₹0</td></tr>
        @if ($gst)<tr><td>CGST</td><td class="mono" data-t="cgst">₹0</td></tr><tr><td>SGST</td><td class="mono" data-t="sgst">₹0</td></tr>@endif
        <tr><td><b>Total</b></td><td class="mono"><b data-t="total">₹0</b></td></tr>
      </table>
    </div>
    @unless ($gst)<p class="hint">No GSTIN in Settings, so these bills carry no GST.</p>@endunless
  </div>

  <div class="card">
    <h2>Payment received now</h2>
    <div class="form">
      <div class="field"><label for="pay_amount">Amount ₹</label><div class="row" style="flex-wrap:nowrap"><input id="pay_amount" name="pay_amount" class="inp mono" inputmode="decimal" placeholder="0" value="{{ old('pay_amount') }}"><button type="button" class="btn sm" data-full>Full</button></div><span class="hint">Leave empty if the patient will pay later.</span></div>
      <div class="field"><label for="pay_mode">Paid by</label><select id="pay_mode" name="pay_mode" class="inp">@foreach (Money::MODES as $k => $l)<option value="{{ $k }}" @selected(old('pay_mode', 'CASH') === $k)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="pay_ref">Reference</label><input id="pay_ref" name="pay_ref" class="inp" placeholder="UPI ref, card last 4 digits, cheque no." value="{{ old('pay_ref') }}"></div>
      <div class="field full"><label for="notes">Note on the bill</label><input id="notes" name="notes" class="inp" maxlength="500" placeholder="Optional, printed on the bill" value="{{ old('notes') }}"></div>
    </div>
  </div>
  <div class="row"><button class="btn pri" data-save>Save bill</button><span class="hint" data-save-hint></span></div>
</form>
@endsection
@push('scripts')<script>window.NEW_PATIENT_URL = @json(route('patients.create'));</script>@endpush
