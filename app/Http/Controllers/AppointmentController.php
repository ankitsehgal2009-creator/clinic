<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Support\Fmt;
use App\Support\Status;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $r)
    {
        $me = $this->me();
        $today = Fmt::today();
        $date = Fmt::isDate($r->query('date')) ? $r->query('date') : $today;
        $view = in_array($r->query('view'), ['week', 'month'], true) ? $r->query('view') : 'day';
        [$from, $to] = [$date, $date];
        if ($view === 'week') {
            $from = Fmt::addDays($date, -((int) date('N', strtotime($date)) - 1));
            $to = Fmt::addDays($from, 6);
        } elseif ($view === 'month') {
            $from = substr($date, 0, 8).'01';
            $to = date('Y-m-t', strtotime($from));
        }
        $appts = Appointment::with(['patient:id,name,code', 'chair:id,name', 'doctor:id,name'])
            ->where('clinic_id', $me->clinic_id)->whereBetween('date', [$from, $to])
            ->orderBy('date')->orderBy('start_min')->get();
        return view('appointments.index', [
            'date' => $date, 'view' => $view, 'from' => $from, 'to' => $to, 'today' => $today, 'appts' => $appts,
            'live' => $appts->where('status', '!=', 'CANCELLED'),
            'chairs' => Chair::where('clinic_id', $me->clinic_id)->where('active', true)->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function create(Request $r)
    {
        $me = $this->me();
        $patient = $r->query('patient') ? Patient::where('clinic_id', $me->clinic_id)->where('active', true)->find($r->query('patient')) : null;
        $date = Fmt::isDate($r->query('date')) && $r->query('date') >= Fmt::today() ? $r->query('date') : Fmt::today();
        return view('appointments.create', [
            'clinic' => $me->clinic,
            'chairs' => Chair::where('clinic_id', $me->clinic_id)->where('active', true)->orderBy('id')->get(['id', 'name']),
            'doctors' => User::where('clinic_id', $me->clinic_id)->where('role', 'DOCTOR')->where('active', true)->get(['id', 'name']),
            'patient' => $patient, 'date' => $date,
        ]);
    }

    public function store(Request $r)
    {
        $me = $this->me();
        $v = $r->validate([
            'patient_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'chair_id' => 'required|integer',
            'start_min' => 'required|integer',
            'doctor_id' => 'nullable|integer',
            'treatment' => 'nullable|string|max:100',
        ], ['patient_id.required' => 'Pick a patient from the suggestions.', 'start_min.required' => 'Pick a free slot.']);
        $patient = Patient::where('clinic_id', $me->clinic_id)->where('active', true)->find($v['patient_id']);
        if (! $patient) {
            return back()->withInput()->with('error', 'Pick a patient from the suggestions.');
        }
        if ($problem = $this->checkSlot($me->clinic, $v['date'], (int) $v['chair_id'], (int) $v['start_min'])) {
            return back()->withInput()->with('error', $problem);
        }
        $doctor = ! empty($v['doctor_id']) ? User::where('clinic_id', $me->clinic_id)->where('role', 'DOCTOR')->find($v['doctor_id']) : null;
        Appointment::create([
            'clinic_id' => $me->clinic_id, 'patient_id' => $patient->id, 'chair_id' => $v['chair_id'], 'doctor_id' => $doctor?->id,
            'date' => $v['date'], 'start_min' => $v['start_min'], 'duration' => $me->clinic->slot_minutes, 'treatment' => $v['treatment'] ?? null,
        ]);
        Activity::log($me, 'Booked appointment', "$patient->name · ".Fmt::date($v['date']).' '.Fmt::time($v['start_min']));
        return redirect()->route('appointments.index', ['date' => $v['date']])->with('ok', "Booked $patient->name at ".Fmt::time($v['start_min']).'.');
    }

    public function status(Request $r, Appointment $appointment)
    {
        $a = $this->own($appointment);
        $s = $r->validate(['status' => 'required|in:'.implode(',', array_keys(Status::STATUS))])['status'];
        $a->update(['status' => $s]);
        Activity::log($this->me(), $s === 'CANCELLED' ? 'Cancelled appointment' : 'Marked '.Status::label($s), $this->describe($a));
        return $r->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function move(Request $r, Appointment $appointment)
    {
        $a = $this->own($appointment);
        $v = $r->validate(['date' => 'required|date_format:Y-m-d', 'chair_id' => 'required|integer', 'start_min' => 'required|integer']);
        if ($problem = $this->checkSlot($this->me()->clinic, $v['date'], (int) $v['chair_id'], (int) $v['start_min'], $a->id)) {
            return response()->json(['error' => $problem], 422);
        }
        $before = $this->describe($a);
        $a->update(['date' => $v['date'], 'chair_id' => $v['chair_id'], 'start_min' => $v['start_min'], 'status' => 'BOOKED']);
        Activity::log($this->me(), 'Rescheduled appointment', $before.' to '.Fmt::date($a->date).' '.Fmt::time($a->start_min));
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $r, Appointment $appointment)
    {
        $a = $this->own($appointment);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can delete permanently.');
        $what = $this->describe($a);
        $a->delete();
        Activity::log($this->me(), 'Deleted appointment permanently', $what);
        return $r->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    private function describe(Appointment $a): string
    {
        return $a->patient->name.' · '.Fmt::date($a->date).' '.Fmt::time($a->start_min);
    }

    /** A readable message if the slot is outside hours, in the past or already taken; null when it is free. */
    public static function checkSlot(Clinic $clinic, string $date, int $chairId, int $start, ?int $ignoreId = null): ?string
    {
        if (! in_array($start, $clinic->slots(), true)) {
            return 'That time is outside clinic hours.';
        }
        $today = Fmt::today();
        if ($date < $today || ($date === $today && $start < Fmt::nowMinutes())) {
            return 'That time has already passed. Pick a later slot.';
        }
        $chair = Chair::where('clinic_id', $clinic->id)->where('active', true)->find($chairId);
        if (! $chair) {
            return 'Pick a chair.';
        }
        $clash = Appointment::with('patient:id,name')
            ->where(['clinic_id' => $clinic->id, 'chair_id' => $chairId, 'date' => $date])
            ->where('status', '!=', 'CANCELLED')->where('start_min', '<=', $start)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->orderByDesc('start_min')->first();
        if ($clash && $clash->start_min + $clash->duration > $start) {
            return "$chair->name is taken at ".Fmt::time($start)." by {$clash->patient->name}. Pick a free slot.";
        }
        return null;
    }
}
