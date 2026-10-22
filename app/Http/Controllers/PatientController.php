<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Clinic;
use App\Models\Patient;
use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public const SCOPES = ['all' => 'All', 'name' => 'Name', 'mobile' => 'Mobile', 'code' => 'ID', 'relative' => 'Father / husband / wife / mother'];

    public function index(Request $r)
    {
        $me = $this->me();
        $term = trim((string) $r->query('q', ''));
        $by = array_key_exists($r->query('by'), self::SCOPES) ? $r->query('by') : 'all';
        $q = Patient::where('clinic_id', $me->clinic_id);
        if ($term !== '') {
            $digits = preg_replace('/\D/', '', $term);
            $like = '%'.addcslashes($term, '%_').'%';
            $q->where(function ($w) use ($by, $like, $digits) {
                $w->whereRaw('1 = 0');
                if (in_array($by, ['all', 'name'])) {
                    $w->orWhere('name', 'like', $like);
                }
                if (in_array($by, ['all', 'mobile']) && strlen($digits) >= 3) {
                    $w->orWhere('mobile', 'like', "%$digits%");
                }
                if (in_array($by, ['all', 'code'])) {
                    $w->orWhere('code', 'like', $like)->orWhere('old_id', 'like', $like);
                }
                if (in_array($by, ['all', 'relative'])) {
                    $w->orWhere('relative_name', 'like', $like);
                }
            });
        }
        return view('patients.index', [
            'rows' => $q->latest('id')->limit(100)->get(),
            'total' => Patient::where('clinic_id', $me->clinic_id)->count(),
            'term' => $term, 'by' => $by,
        ]);
    }

    public function create()
    {
        $c = $this->me()->clinic;
        return view('patients.create', ['nextCode' => $c->patient_prefix.str_pad($c->next_patient_no, 4, '0', STR_PAD_LEFT)]);
    }

    public function store(Request $r)
    {
        $me = $this->me();
        $v = $this->validated($r);

        $patient = DB::transaction(fn () => Patient::create([
            'clinic_id' => $me->clinic_id,
            'code' => self::nextCode($me->clinic_id),
            'name' => $v['name'],
            'mobile' => Fmt::mobile($v['mobile']),
            'gender' => $v['gender'] ?? null,
            'dob' => $v['dob'] ?? null,
            'age' => ! empty($v['dob']) ? Fmt::ageFrom($v['dob']) : ($v['age'] ?? null),
            'relation_type' => $v['relation_type'] ?? null,
            'relative_name' => $v['relative_name'] ?? null,
            'address' => $v['address'] ?? null,
            'medical_history' => array_values(array_unique($v['history'] ?? [])),
        ]));
        Activity::log($me, 'Added patient', "$patient->name · $patient->code");
        return $r->input('then') === 'book'
            ? redirect()->route('appointments.create', ['patient' => $patient->id])
            : redirect()->route('patients.show', $patient);
    }

    private function validated(Request $r): array
    {
        return $r->validate([
            'name' => 'required|string|min:2|max:150',
            'mobile' => ['required', self::mobileRule()],
            'gender' => 'nullable|in:Male,Female,Other',
            'dob' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'age' => 'nullable|integer|min:0|max:120',
            'relation_type' => 'nullable|in:FATHER,HUSBAND,WIFE,MOTHER',
            'relative_name' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:250',
            'history' => 'nullable|array',
            'history.*' => 'string|max:100',
        ], ['name.min' => "Enter the patient's full name."]);
    }

    public function edit(Patient $patient)
    {
        $this->own($patient);
        abort_unless($this->me()->canEditPatients(), 403, 'Only the admin and doctors can change patient details.');
        return view('patients.create', ['p' => $patient, 'nextCode' => $patient->code]);
    }

    public function update(Request $r, Patient $patient)
    {
        $this->own($patient);
        $me = $this->me();
        abort_unless($me->canEditPatients(), 403, 'Only the admin and doctors can change patient details.');
        $v = $this->validated($r);
        $patient->fill([
            'name' => $v['name'],
            'mobile' => Fmt::mobile($v['mobile']),
            'gender' => $v['gender'] ?? null,
            'dob' => $v['dob'] ?? null,
            'age' => ! empty($v['dob']) ? Fmt::ageFrom($v['dob']) : ($v['age'] ?? null),
            'relation_type' => $v['relation_type'] ?? null,
            'relative_name' => $v['relative_name'] ?? null,
            'address' => $v['address'] ?? null,
            'medical_history' => array_values(array_unique($v['history'] ?? [])),
        ]);
        $labels = ['name' => 'name', 'mobile' => 'mobile', 'gender' => 'gender', 'dob' => 'date of birth', 'age' => 'age', 'relation_type' => 'relation', 'relative_name' => "relative's name", 'address' => 'address', 'medical_history' => 'medical history'];
        $changed = array_values(array_intersect_key($labels, $patient->getDirty()));
        if ($changed) {
            $old = $patient->getOriginal('name');
            $patient->save();
            Activity::log($me, 'Edited patient', "$patient->code · ".($old !== $patient->name ? "$old → $patient->name" : $patient->name).' · changed '.implode(', ', $changed));
            return redirect()->route('patients.show', $patient)->with('ok', 'Patient details saved.');
        }
        return redirect()->route('patients.show', $patient)->with('ok', 'Nothing was changed.');
    }

    /** Next patient ID for a clinic, e.g. SC-0153. Call inside a transaction: the clinic row is locked. */
    public static function nextCode(int $clinicId): string
    {
        $c = Clinic::whereKey($clinicId)->lockForUpdate()->first();
        $no = $c->next_patient_no;
        $c->increment('next_patient_no');
        return $c->patient_prefix.str_pad($no, 4, '0', STR_PAD_LEFT);
    }

    public function show(Patient $patient)
    {
        $this->own($patient);
        return view('patients.show', [
            'p' => $patient,
            'appts' => $patient->appointments()->with('chair', 'doctor')->orderByDesc('date')->orderByDesc('start_min')->limit(50)->get(),
            'bills' => $this->me()->canOpen('billing') ? $patient->invoices()->orderByDesc('date')->orderByDesc('id')->limit(50)->get() : null,
            'balance' => $patient->balance(),
            'visits' => $this->me()->canOpen('prescriptions') ? \App\Models\Visit::with('doctor:id,name')->withCount(['files as rx_photos' => fn ($q) => $q->where('kind', 'RX'), 'files as xrays' => fn ($q) => $q->where('kind', 'XRAY')])->where('patient_id', $patient->id)->orderByDesc('date')->orderByDesc('id')->limit(50)->get() : null,
            'photos' => $this->me()->canOpen('prescriptions') ? \App\Models\VisitFile::with('uploader:id,name')->join('visits', 'visits.id', '=', 'visit_files.visit_id')
                ->where('visit_files.patient_id', $patient->id)->where('visit_files.clinic_id', $patient->clinic_id)
                ->orderByDesc('visits.date')->orderByDesc('visit_files.id')->get(['visit_files.*', 'visits.date as visit_date', 'visits.procedure as visit_what', 'visits.complaint as visit_cc']) : collect(),
            'chart' => $this->me()->canOpen('teeth') ? ToothController::chartData($patient, $this->me()) : null,
            'family' => Patient::where('clinic_id', $patient->clinic_id)->where('mobile', $patient->mobile)->whereKeyNot($patient->id)->get(['id', 'name', 'code']),
        ]);
    }

    public function active(Request $r, Patient $patient)
    {
        $this->own($patient);
        $patient->update(['active' => $r->boolean('active')]);
        Activity::log($this->me(), $patient->active ? 'Enabled patient' : 'Disabled patient', "$patient->name · $patient->code");
        return $r->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function destroy(Patient $patient)
    {
        $this->own($patient);
        abort_unless($this->me()->isAdmin(), 403, 'Only the admin can delete permanently.');
        DB::transaction(function () use ($patient) {
            $patient->appointments()->delete();
            $patient->invoices()->delete(); // payments go with their bills
            \App\Models\VisitFile::where('patient_id', $patient->id)->get()->each->purge();
            \App\Models\Visit::where('patient_id', $patient->id)->delete(); // medicines go with their visits
            \App\Models\ToothMark::where('patient_id', $patient->id)->delete();
            $patient->delete();
        });
        Activity::log($this->me(), 'Deleted patient permanently', "$patient->name · $patient->code");
        return redirect()->route('patients.index')->with('ok', "$patient->name was deleted.");
    }
}
