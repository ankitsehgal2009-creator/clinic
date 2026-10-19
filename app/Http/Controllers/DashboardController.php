<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Support\Fmt;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $me = $this->me();
        $today = Fmt::today();
        $appts = Appointment::with('patient:id,name', 'chair:id,name')->where('clinic_id', $me->clinic_id)->where('date', $today)->orderBy('start_min')->get();
        return view('dashboard', [
            'today' => $today,
            'appts' => $appts,
            'live' => $appts->where('status', '!=', 'CANCELLED')->count(),
            'count' => fn ($s) => $appts->where('status', $s)->count(),
            'newThisMonth' => Patient::where('clinic_id', $me->clinic_id)->where('created_at', '>=', date('Y-m-01 00:00:00'))->count(),
            'total' => Patient::where('clinic_id', $me->clinic_id)->count(),
        ]);
    }
}
