<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Chair;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show()
    {
        $me = $this->me();
        return view('settings', [
            'c' => $me->clinic,
            'chairs' => Chair::where('clinic_id', $me->clinic_id)->where('active', true)->count(),
            'sample' => ['pt' => (object) ['name' => 'Rohan Deshpande', 'age' => 34, 'gender' => 'Male', 'code' => $me->clinic->patient_prefix.'0142'],
        'v' => ['date' => \App\Support\Fmt::today(), 'complaint' => 'Follow-up for root canal', 'teeth' => '36', 'procedure' => 'RCT sitting 2', 'diagnosis' => 'Irreversible pulpitis', 'advice' => 'Avoid chewing hard food on the treated side until the crown is fitted.', 'next_visit' => date('Y-m-d', strtotime('+7 days'))],
        'meds' => [['name' => 'Amoxicillin 500 mg', 'form' => 'Capsule', 'dose' => '1-1-1', 'timing' => 'After food', 'days' => '5 days'], ['name' => 'Ibuprofen 400 mg + Paracetamol 325 mg', 'form' => 'Tablet', 'dose' => '1-0-1', 'timing' => 'After food', 'days' => '3 days'], ['name' => 'Chlorhexidine 0.2%', 'form' => 'Mouthwash', 'dose' => '10 ml twice', 'timing' => 'After brushing', 'days' => '7 days']]],
            'doc' => \App\Models\User::where(['clinic_id' => $me->clinic_id, 'role' => 'DOCTOR'])->orderByDesc('is_owner')->first(),
        ]);
    }

    public function save(Request $r)
    {
        $me = $this->me();
        abort_unless($me->canManageClinic(), 403, 'Only the admin or the owner doctor can change clinic settings.');
        $time = ['required', 'regex:/^\d{2}:\d{2}$/'];
        $v = $r->validate([
            'name' => 'required|string|min:2|max:150',
            'address' => 'required|string|min:3|max:250',
            'city' => 'required|string|min:2|max:100',
            'phone' => 'required|string|min:6|max:30',
            'gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'morning_open' => $time, 'morning_close' => $time, 'evening_open' => $time, 'evening_close' => $time,
            'chairs' => 'required|integer|min:1|max:20',
            'invoice_prefix' => 'required|alpha_num|max:8',
            'rx_design' => 'nullable|in:'.implode(',', array_keys(\App\Support\Rx::DESIGNS)),
        ], ['gstin.regex' => 'GSTIN should be 15 characters, like 27AAKFS4821M1Z6.']);
        $min = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        [$mo, $mc, $eo, $ec] = array_map($min, [$v['morning_open'], $v['morning_close'], $v['evening_open'], $v['evening_close']]);
        if (! ($mo < $mc && $mc <= $eo && $eo < $ec)) {
            $msg = 'Hours must go in order: opens, break starts, break ends, closes.';
            return $r->expectsJson() ? response()->json(['error' => $msg], 422) : back()->withInput()->with('error', $msg);
        }
        $me->clinic->update([
            'name' => $v['name'], 'address' => $v['address'], 'city' => $v['city'], 'phone' => $v['phone'],
            'gstin' => $v['gstin'] ? strtoupper($v['gstin']) : null, 'invoice_prefix' => strtoupper($v['invoice_prefix']),
            'rx_design' => $v['rx_design'] ?? $me->clinic->rx_design,
            'morning_open' => $mo, 'morning_close' => $mc, 'evening_open' => $eo, 'evening_close' => $ec,
        ]);
        $have = Chair::where('clinic_id', $me->clinic_id)->orderBy('id')->get();
        for ($i = $have->count(); $i < $v['chairs']; $i++) {
            Chair::create(['clinic_id' => $me->clinic_id, 'name' => 'Chair '.($i + 1)]);
        }
        foreach ($have as $i => $chair) {
            $chair->update(['active' => $i < $v['chairs']]);
        }
        $changed = array_keys($me->clinic->getChanges());
        Activity::log($me, 'Changed clinic settings');
        return $r->expectsJson() ? response()->json(['ok' => true, 'changed' => $changed]) : back()->with('ok', 'Settings saved.');
    }

    /** Clinic logo, printed on bills and shown in the menu. Empty removes it. */
    public function logo(Request $r)
    {
        $me = $this->me();
        abort_unless($me->canManageClinic(), 403, 'Only the admin or the owner doctor can change the clinic logo.');
        $v = $r->validate(['logo' => ['nullable', 'string', 'max:400000', self::imageRule()]], ['logo.max' => 'That logo is too large. Try a smaller picture.']);
        $me->clinic->update(['logo' => $v['logo'] ?: null]);
        Activity::log($me, $v['logo'] ? 'Changed clinic logo' : 'Removed clinic logo');
        return response()->json(['ok' => true]);
    }
}
