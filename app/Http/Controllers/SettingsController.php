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
        ]);
    }

    public function save(Request $r)
    {
        $me = $this->me();
        abort_unless($me->isAdmin(), 403, 'Only the admin can change clinic settings.');
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
        ], ['gstin.regex' => 'GSTIN should be 15 characters, like 27AAKFS4821M1Z6.']);
        $min = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        [$mo, $mc, $eo, $ec] = array_map($min, [$v['morning_open'], $v['morning_close'], $v['evening_open'], $v['evening_close']]);
        if (! ($mo < $mc && $mc <= $eo && $eo < $ec)) {
            return back()->withInput()->with('error', 'Hours must go in order: opens, break starts, break ends, closes.');
        }
        $me->clinic->update([
            'name' => $v['name'], 'address' => $v['address'], 'city' => $v['city'], 'phone' => $v['phone'],
            'gstin' => $v['gstin'] ? strtoupper($v['gstin']) : null, 'invoice_prefix' => strtoupper($v['invoice_prefix']),
            'morning_open' => $mo, 'morning_close' => $mc, 'evening_open' => $eo, 'evening_close' => $ec,
        ]);
        $have = Chair::where('clinic_id', $me->clinic_id)->orderBy('id')->get();
        for ($i = $have->count(); $i < $v['chairs']; $i++) {
            Chair::create(['clinic_id' => $me->clinic_id, 'name' => 'Chair '.($i + 1)]);
        }
        foreach ($have as $i => $chair) {
            $chair->update(['active' => $i < $v['chairs']]);
        }
        Activity::log($me, 'Changed clinic settings');
        return back()->with('ok', 'Settings saved.');
    }
}
