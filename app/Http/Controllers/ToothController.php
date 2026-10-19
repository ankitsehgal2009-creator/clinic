<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Patient;
use App\Models\ToothMark;
use App\Models\User;
use App\Models\Visit;
use App\Support\Fmt;
use App\Support\Teeth;
use Illuminate\Http\Request;

/** Tooth chart: what state each tooth is in, with notes and the visits that treated it. */
class ToothController extends Controller
{
    public function index(Request $r)
    {
        $me = $this->me();
        $patient = $r->query('patient') ? $this->own(Patient::findOrFail((int) $r->query('patient'))) : null;
        return view('teeth.index', ['patient' => $patient, 'chart' => $patient ? self::chartData($patient, $me) : null]);
    }

    /** Everything the chart partial needs for one patient. */
    public static function chartData(Patient $p, User $me): array
    {
        $marks = ToothMark::with('updater:id,name')->where('patient_id', $p->id)->get()->keyBy('tooth');
        $history = [];
        foreach (Visit::where('patient_id', $p->id)->whereNotNull('teeth')->orderByDesc('date')->get(['id', 'date', 'teeth', 'procedure', 'complaint', 'diagnosis']) as $v) {
            foreach (Teeth::parse($v->teeth) as $n) {
                $history[$n][] = ['date' => Fmt::date($v->date), 'what' => $v->procedure ?: $v->diagnosis ?: $v->complaint ?: 'Visit', 'id' => $v->id];
            }
        }
        $data = [];
        foreach ($marks as $n => $m) {
            $data[$n] = ['state' => $m->state, 'note' => $m->note, 'by' => $m->updater?->name, 'at' => $m->updated_at->format('j M Y')];
        }
        return [
            'patient' => $p,
            'marks' => $data,
            'history' => $history,
            'child' => $p->age !== null && $p->age < 12,
            'canEdit' => $me->isAdmin() || $me->role === 'DOCTOR',
            'rxUrl' => $me->canOpen('prescriptions') ? route('rx.create', ['patient' => $p->id]) : null,
        ];
    }

    public function save(Request $r, Patient $patient)
    {
        $this->own($patient);
        $me = $this->me();
        abort_unless($me->isAdmin() || $me->role === 'DOCTOR', 403, 'Only a doctor or the admin can mark teeth.');
        $v = $r->validate([
            'tooth' => 'required|integer',
            'state' => 'required|in:'.implode(',', array_keys(Teeth::STATES)),
            'note' => 'nullable|string|max:250',
        ]);
        abort_unless(Teeth::valid((int) $v['tooth']), 422, 'That is not a tooth number.');
        if ($v['state'] === 'healthy' && blank($v['note'] ?? null)) {
            ToothMark::where(['patient_id' => $patient->id, 'tooth' => $v['tooth']])->delete();
        } else {
            ToothMark::updateOrCreate(
                ['patient_id' => $patient->id, 'tooth' => $v['tooth']],
                ['clinic_id' => $me->clinic_id, 'state' => $v['state'], 'note' => $v['note'] ?? null, 'updated_by' => $me->id],
            );
        }
        Activity::log($me, 'Marked tooth', "$patient->name · tooth {$v['tooth']} · ".Teeth::STATES[$v['state']].($v['note'] ?? '' ? ' · '.$v['note'] : ''));
        return response()->json(['ok' => true, 'by' => $me->name, 'at' => now()->format('j M Y')]);
    }
}
