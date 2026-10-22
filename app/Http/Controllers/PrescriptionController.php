<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Patient;
use App\Models\RxTemplate;
use App\Models\TreatmentPrice;
use App\Models\User;
use App\Models\Visit;
use App\Support\Fmt;
use App\Support\Rx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Visits and the prescriptions printed from them, plus reusable prescription templates. */
class PrescriptionController extends Controller
{
    public function index(Request $r)
    {
        $me = $this->me();
        $term = trim((string) $r->query('q', ''));
        $q = Visit::with('patient:id,name,code', 'doctor:id,name')->withCount('medicines')->where('clinic_id', $me->clinic_id);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_').'%';
            $q->whereHas('patient', fn ($p) => $p->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('mobile', 'like', $like));
        }
        return view('rx.index', [
            'rows' => $q->orderByDesc('date')->orderByDesc('id')->limit(200)->get(),
            'term' => $term,
            'today' => Visit::where(['clinic_id' => $me->clinic_id, 'date' => Fmt::today()])->count(),
        ]);
    }

    public function create(Request $r)
    {
        $me = $this->me();
        $patient = $r->query('patient') ? $this->own(Patient::findOrFail((int) $r->query('patient'))) : null;
        return view('rx.form', $this->formData($me, $patient) + ['visit' => null, 'patient' => $patient, 'tooth' => $r->query('tooth')]);
    }

    public function edit(Visit $visit)
    {
        $this->own($visit);
        $visit->load('patient', 'medicines', 'files');
        return view('rx.form', $this->formData($this->me(), $visit->patient) + ['visit' => $visit, 'patient' => $visit->patient, 'tooth' => null]);
    }

    private function formData(User $me, ?Patient $patient = null): array
    {
        $templates = RxTemplate::where('clinic_id', $me->clinic_id)->where('active', true)->orderBy('name')->get();
        return [
            'templates' => $templates,
            'tplData' => $templates->mapWithKeys(fn ($t) => [$t->id => ['meds' => $t->meds, 'advice' => $t->advice]]),
            'defaultTpl' => $me->clinic->rx_template_id,
            'doctors' => User::where(['clinic_id' => $me->clinic_id, 'role' => 'DOCTOR', 'active' => true])->orderBy('name')->get(['id', 'name']),
            'procedures' => \App\Models\Procedure::where('clinic_id', $me->clinic_id)->where('active', true)->orderBy('name')->pluck('name')
                ->merge(TreatmentPrice::where('clinic_id', $me->clinic_id)->where('active', true)->pluck('name'))->unique(fn ($n) => mb_strtolower($n))->sort()->values(),
            'today' => Fmt::today(),
            'chairs' => \App\Models\Chair::where('clinic_id', $me->clinic_id)->where('active', true)->orderBy('id')->get(['id', 'name']),
            'upcoming' => $patient ? \App\Models\Appointment::where(['clinic_id' => $me->clinic_id, 'patient_id' => $patient->id])->where('date', '>=', Fmt::today())->where('status', '!=', 'CANCELLED')->orderBy('date')->orderBy('start_min')->limit(3)->get(['date', 'start_min']) : collect(),
        ];
    }

    /** Checks the next-visit slot before anything is saved. Returns [error, booking data]. */
    private function nextBooking(Request $r, User $me, array $v): array
    {
        if (! $r->boolean('book_next') || ! $me->canOpen('appointments')) {
            return [null, null];
        }
        if (empty($v['next_visit'])) {
            return ['Pick the next visit date to book the appointment.', null];
        }
        if (! $r->filled('appt_min')) {
            return ['Pick a free time for the next visit, or untick "Book the appointment".', null];
        }
        $b = ['date' => $v['next_visit'], 'chair_id' => (int) $r->input('appt_chair'), 'start_min' => (int) $r->input('appt_min'), 'treatment' => mb_substr(trim((string) $r->input('appt_treatment')) ?: 'Follow-up', 0, 100)];
        $problem = AppointmentController::checkSlot($me->clinic, $b['date'], $b['chair_id'], $b['start_min']);
        return [$problem, $problem ? null : $b];
    }

    private function bookNext(User $me, Patient $patient, ?int $doctorId, ?array $b): string
    {
        if (! $b) {
            return '';
        }
        \App\Models\Appointment::create($b + ['clinic_id' => $me->clinic_id, 'patient_id' => $patient->id, 'doctor_id' => $doctorId, 'duration' => $me->clinic->slot_minutes]);
        Activity::log($me, 'Booked appointment', "$patient->name · ".Fmt::date($b['date']).' '.Fmt::time($b['start_min']).' · from visit');
        return ' Next visit booked for '.Fmt::date($b['date']).' at '.Fmt::time($b['start_min']).'.';
    }

    public function store(Request $r)
    {
        $me = $this->me();
        [$v, $meds] = $this->validated($r);
        $patient = $this->own(Patient::findOrFail($v['patient_id']));
        [$problem, $booking] = $this->nextBooking($r, $me, $v);
        if ($problem) {
            return back()->withInput()->with('error', $problem);
        }
        $visit = DB::transaction(function () use ($me, $v, $meds) {
            $visit = Visit::create($v + ['clinic_id' => $me->clinic_id, 'created_by' => $me->id]);
            $visit->medicines()->createMany($meds);
            if ($v['rx_template_id']) {
                RxTemplate::where(['id' => $v['rx_template_id'], 'clinic_id' => $me->clinic_id])->increment('uses');
            }
            return $visit;
        });
        Activity::log($me, 'Saved visit', "$patient->name · ".($v['procedure'] ?: $v['complaint'] ?: 'Visit').' · '.count($meds).' medicines');
        $booked = $this->bookNext($me, $patient, $visit->doctor_id, $booking);
        return redirect()->route('rx.show', $visit)->with('ok', 'Visit saved.'.$booked.' Print the prescription below.');
    }

    public function update(Request $r, Visit $visit)
    {
        $this->own($visit);
        $me = $this->me();
        [$v, $meds] = $this->validated($r);
        unset($v['patient_id']);
        [$problem, $booking] = $this->nextBooking($r, $me, $v);
        if ($problem) {
            return back()->withInput()->with('error', $problem);
        }
        DB::transaction(function () use ($visit, $v, $meds) {
            $visit->update($v);
            $visit->medicines()->delete();
            $visit->medicines()->createMany($meds);
        });
        Activity::log($me, 'Changed visit', $visit->patient->name.' · '.Fmt::date($visit->date));
        $booked = $this->bookNext($me, $visit->patient, $visit->doctor_id, $booking);
        return redirect()->route('rx.show', $visit)->with('ok', 'Visit updated.'.$booked);
    }

    /** Visit fields and the medicine rows that have a name. */
    private function validated(Request $r): array
    {
        $me = $this->me();
        $v = $r->validate([
            'patient_id' => 'required|integer',
            'doctor_id' => 'nullable|integer',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'complaint' => 'nullable|string|max:250',
            'teeth' => 'nullable|string|max:60',
            'procedure' => 'nullable|string|max:250',
            'findings' => 'nullable|string|max:3000',
            'diagnosis' => 'nullable|string|max:250',
            'advice' => 'nullable|string|max:2000',
            'next_visit' => 'nullable|date_format:Y-m-d|after_or_equal:date',
            'design' => 'required|in:'.implode(',', array_keys(Rx::DESIGNS)),
            'rx_template_id' => 'nullable|integer',
            'meds' => 'nullable|array|max:20',
            'meds.*.name' => 'nullable|string|max:150',
            'meds.*.form' => 'nullable|string|max:20',
            'meds.*.dose' => 'nullable|string|max:40',
            'meds.*.timing' => 'nullable|string|max:60',
            'meds.*.days' => 'nullable|string|max:30',
        ], [
            'patient_id.required' => 'Pick the patient first. Type 3 letters of the name and choose from the list.',
            'next_visit.after_or_equal' => 'The next visit must be on or after the visit date.',
        ]);
        if (! empty($v['doctor_id']) && ! User::where(['id' => $v['doctor_id'], 'clinic_id' => $me->clinic_id])->exists()) {
            $v['doctor_id'] = null;
        }
        $v['doctor_id'] = ($v['doctor_id'] ?? null) ?: ($me->role === 'DOCTOR' ? $me->id : null);
        $meds = array_values(array_filter(array_map(fn ($m) => array_map(fn ($x) => is_string($x) ? trim($x) : $x, array_intersect_key($m, array_flip(['name', 'form', 'dose', 'timing', 'days']))), $v['meds'] ?? []), fn ($m) => ($m['name'] ?? '') !== ''));
        unset($v['meds']);
        foreach (['complaint', 'teeth', 'procedure', 'findings', 'diagnosis', 'advice', 'next_visit', 'rx_template_id'] as $k) {
            $v[$k] = ($v[$k] ?? '') === '' ? null : $v[$k];
        }
        return [$v, $meds];
    }

    public function show(Request $r, Visit $visit)
    {
        $this->own($visit);
        $visit->load('patient', 'doctor', 'medicines', 'files');
        $design = array_key_exists($r->query('design'), Rx::DESIGNS) ? $r->query('design') : $visit->design;
        return view('rx.show', ['visit' => $visit, 'design' => $design, 'c' => $this->me()->clinic]);
    }

    // ---------- Procedure list ----------

    public function procedures()
    {
        $me = $this->me();
        return view('rx.procedures', ['all' => \App\Models\Procedure::where('clinic_id', $me->clinic_id)->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function addProcedure(Request $r)
    {
        $me = $this->me();
        $name = trim(preg_replace('/\s+/', ' ', (string) $r->validate(['name' => 'required|string|min:2|max:120'])['name']));
        $found = \App\Models\Procedure::where('clinic_id', $me->clinic_id)->where('name', $name)->first();
        if ($found) {
            $found->update(['active' => true]);
            return back()->with('ok', "$name is already in the list and is turned on.");
        }
        \App\Models\Procedure::create(['clinic_id' => $me->clinic_id, 'name' => $name]);
        Activity::log($me, 'Added procedure', $name);
        return back()->with('ok', "$name added.");
    }

    public function procedureActive(Request $r, \App\Models\Procedure $procedure)
    {
        $this->own($procedure);
        $procedure->update(['active' => $r->boolean('active')]);
        Activity::log($this->me(), $procedure->active ? 'Turned on procedure' : 'Turned off procedure', $procedure->name);
        return response()->json(['ok' => true]);
    }

    public function destroyProcedure(\App\Models\Procedure $procedure)
    {
        $this->own($procedure);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can delete permanently.');
        $procedure->delete();
        Activity::log($this->me(), 'Deleted procedure', $procedure->name);
        return back()->with('ok', "$procedure->name deleted. Old visits keep it.");
    }

    // ---------- Templates ----------

    public function templates(Request $r)
    {
        $me = $this->me();
        $all = RxTemplate::where('clinic_id', $me->clinic_id)->orderByDesc('active')->orderBy('name')->get();
        $edit = $r->query('new') ? null : ($all->firstWhere('id', (int) $r->query('t')) ?? $all->first());
        return view('rx.templates', ['all' => $all, 'edit' => $edit, 'defaultTpl' => $me->clinic->rx_template_id]);
    }

    public function saveTemplate(Request $r, ?RxTemplate $template = null)
    {
        $me = $this->me();
        if ($template?->exists) {
            $this->own($template);
        }
        $v = $r->validate([
            'name' => 'required|string|min:2|max:80',
            'advice' => 'nullable|string|max:2000',
            'meds' => 'nullable|array|max:20',
            'meds.*.name' => 'nullable|string|max:150',
            'meds.*.form' => 'nullable|string|max:20',
            'meds.*.dose' => 'nullable|string|max:40',
            'meds.*.timing' => 'nullable|string|max:60',
            'meds.*.days' => 'nullable|string|max:30',
        ]);
        $meds = array_values(array_filter(array_map(fn ($m) => array_intersect_key($m, array_flip(['name', 'form', 'dose', 'timing', 'days'])), $v['meds'] ?? []), fn ($m) => trim($m['name'] ?? '') !== ''));
        $data = ['name' => $v['name'], 'advice' => $v['advice'] ?? null, 'meds' => $meds];
        if ($template?->exists) {
            $template->update($data);
            Activity::log($me, 'Changed prescription template', $template->name);
        } else {
            $template = RxTemplate::create($data + ['clinic_id' => $me->clinic_id]);
            Activity::log($me, 'Added prescription template', $template->name);
        }
        if ($r->expectsJson()) {
            return response()->json(['id' => $template->id, 'name' => $template->name, 'meds' => $template->meds, 'advice' => $template->advice]);
        }
        return redirect()->route('rx.templates', ['t' => $template->id])->with('ok', "$template->name saved.");
    }

    public function templateActive(Request $r, RxTemplate $template)
    {
        $this->own($template);
        $template->update(['active' => $r->boolean('active')]);
        Activity::log($this->me(), $template->active ? 'Enabled prescription template' : 'Disabled prescription template', $template->name);
        return $r->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function templateDefault(RxTemplate $template)
    {
        $this->own($template);
        $template->update(['active' => true]);
        $this->me()->clinic->update(['rx_template_id' => $template->id]);
        Activity::log($this->me(), 'Set default prescription template', $template->name);
        return back()->with('ok', "$template->name now loads first on every new visit.");
    }

    public function destroyTemplate(RxTemplate $template)
    {
        $this->own($template);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can delete permanently.');
        $template->delete();
        if ((int) $this->me()->clinic->rx_template_id === $template->id) {
            $this->me()->clinic->update(['rx_template_id' => null]);
        }
        Activity::log($this->me(), 'Deleted prescription template', $template->name);
        return redirect()->route('rx.templates')->with('ok', "$template->name was deleted.");
    }
}
