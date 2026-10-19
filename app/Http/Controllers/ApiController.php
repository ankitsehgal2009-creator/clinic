<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Support\Fmt;
use Illuminate\Http\Request;

/** Small JSON lookups used by the booking screen and the date picker. */
class ApiController extends Controller
{
    /** Name suggestions for booking: starts after 3 letters, active patients only. */
    public function search(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 3) {
            return [];
        }
        return Patient::where('clinic_id', $this->me()->clinic_id)->where('active', true)
            ->where('name', 'like', '%'.addcslashes($q, '%_').'%')
            ->orderBy('name')->limit(8)
            ->get(['id', 'code', 'name', 'mobile', 'age', 'relation_type', 'relative_name']);
    }

    /** Every slot of the day for one chair: free, taken (with patient) or past. */
    public function slots(Request $r)
    {
        $date = $r->query('date');
        $chair = (int) $r->query('chair');
        if (! Fmt::isDate($date) || ! $chair) {
            return response()->json(['error' => 'date and chair are required'], 400);
        }
        $me = $this->me();
        $appts = Appointment::with('patient:id,name')
            ->where(['clinic_id' => $me->clinic_id, 'chair_id' => $chair, 'date' => $date])
            ->where('status', '!=', 'CANCELLED')
            ->when($r->query('ignore'), fn ($q, $id) => $q->whereKeyNot((int) $id))
            ->get();
        $today = Fmt::today();
        $now = Fmt::nowMinutes();
        return array_map(function ($m) use ($appts, $date, $today, $now) {
            $a = $appts->first(fn ($x) => $m >= $x->start_min && $m < $x->start_min + $x->duration);
            return ['m' => $m, 'taken' => $a?->patient->name, 'past' => $date < $today || ($date === $today && $m < $now)];
        }, $me->clinic->slots());
    }

    /** { "2026-10-18": 16, ... } for one month, not counting cancelled. */
    public function counts(Request $r)
    {
        $month = (string) $r->query('month');
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['error' => 'month must be YYYY-MM'], 400);
        }
        return (object) Appointment::where('clinic_id', $this->me()->clinic_id)
            ->where('date', 'like', "$month-%")->where('status', '!=', 'CANCELLED')
            ->groupBy('date')->selectRaw('date, count(*) as n')->pluck('n', 'date')->all();
    }
}
