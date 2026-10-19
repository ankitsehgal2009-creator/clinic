<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Online booking: the clinic's settings and QR card, and the public page patients book from. */
class BookingController extends Controller
{
    public function index()
    {
        $me = $this->me();
        $c = $me->clinic;
        if (! $c->booking_slug) {
            $c->update(['booking_slug' => self::freeSlug($c->name)]);
        }
        return view('booking.index', [
            'c' => $c,
            'link' => route('book.page', $c->booking_slug),
            'chairs' => Chair::where(['clinic_id' => $c->id, 'active' => true])->orderBy('id')->get(),
            'online' => Appointment::with('patient:id,name,code,mobile', 'chair:id,name')->where(['clinic_id' => $c->id, 'source' => 'ONLINE'])
                ->where('date', '>=', Fmt::today())->orderBy('date')->orderBy('start_min')->limit(100)->get(),
            'canManage' => $me->canManageClinic(),
            'today' => Fmt::today(),
        ]);
    }

    public function save(Request $r)
    {
        $me = $this->me();
        abort_unless($me->canManageClinic(), 403, 'Only the admin or the owner doctor can change online booking.');
        $v = $r->validate([
            'booking_on' => 'required|boolean',
            'booking_approval' => 'required|boolean',
            'booking_days' => 'required|integer|min:1|max:90',
            'booking_slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9-]+$/'],
        ], [
            'booking_slug.regex' => 'The link name can only have small letters, numbers and dashes, like smilecraft-andheri.',
            'booking_days.max' => 'Patients can book up to 90 days ahead.',
        ]);
        if (Clinic::where('booking_slug', $v['booking_slug'])->whereKeyNot($me->clinic_id)->exists()) {
            return response()->json(['error' => 'Another clinic already uses that link name. Try adding your area, like '.$v['booking_slug'].'-andheri.'], 422);
        }
        $me->clinic->update($v);
        Activity::log($me, 'Changed online booking', ($v['booking_on'] ? 'On' : 'Off').' · '.$v['booking_days'].' days ahead'.($v['booking_approval'] ? ' · reception confirms' : ''));
        return response()->json(['ok' => true, 'link' => route('book.page', $v['booking_slug'])]);
    }

    /** A link name made from the clinic name that no other clinic uses: "Smile Craft Dental Clinic" becomes "smile-craft-dental-clinic". */
    public static function freeSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 34, '') ?: 'clinic';
        $slug = $base;
        for ($n = 2; Clinic::where('booking_slug', $slug)->exists(); $n++) {
            $slug = "$base-$n";
        }
        return $slug;
    }

    // ---------- Public page for patients ----------

    private function clinicFor(string $slug): Clinic
    {
        $c = Clinic::where('booking_slug', $slug)->first();
        abort_unless($c, 404);
        return $c;
    }

    /** Days patients may pick, today first. */
    private static function days(Clinic $c): array
    {
        $out = [];
        $d = new \DateTimeImmutable(Fmt::today());
        for ($i = 0; $i < $c->booking_days; $i++) {
            $out[] = $d->modify("+$i day")->format('Y-m-d');
        }
        return $out;
    }

    /** Start minutes still open on a day: not past, and at least one chair free. */
    private static function openTimes(Clinic $c, string $date): array
    {
        $chairs = Chair::where(['clinic_id' => $c->id, 'active' => true])->pluck('id')->all();
        if (! $chairs) {
            return [];
        }
        $appts = Appointment::where(['clinic_id' => $c->id, 'date' => $date])->where('status', '!=', 'CANCELLED')->get(['chair_id', 'start_min', 'duration']);
        $now = $date === Fmt::today() ? Fmt::nowMinutes() : -1;
        $out = [];
        foreach ($c->slots() as $m) {
            if ($m <= $now) {
                continue;
            }
            $busy = $appts->filter(fn ($a) => $m >= $a->start_min && $m < $a->start_min + $a->duration)->pluck('chair_id')->all();
            if (array_diff($chairs, $busy)) {
                $out[] = $m;
            }
        }
        return $out;
    }

    public function page(string $slug)
    {
        $c = $this->clinicFor($slug);
        return view('booking.public', ['c' => $c, 'days' => self::days($c), 'doctor' => User::where(['clinic_id' => $c->id, 'role' => 'DOCTOR', 'active' => true])->orderByDesc('is_owner')->first()]);
    }

    public function freeSlots(Request $r, string $slug)
    {
        $c = $this->clinicFor($slug);
        $date = (string) $r->query('date');
        if (! $c->booking_on || ! in_array($date, self::days($c), true)) {
            return response()->json([]);
        }
        return response()->json(self::openTimes($c, $date));
    }

    public function book(Request $r, string $slug)
    {
        $c = $this->clinicFor($slug);
        if (! $c->booking_on) {
            return back()->with('error', 'This clinic is not taking online bookings right now. Please call them.');
        }
        if (filled($r->input('website'))) { // hidden field only robots fill in
            return back();
        }
        $v = $r->validate([
            'name' => 'required|string|min:2|max:100',
            'mobile' => ['required', self::mobileRule()],
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|integer',
            'reason' => 'nullable|string|max:100',
        ], ['name.required' => 'Please write your full name.', 'time.required' => 'Please pick a time.']);
        if (! in_array($v['date'], self::days($c), true)) {
            return back()->withInput()->with('error', 'Please pick one of the days shown.');
        }
        $mobile = Fmt::mobile($v['mobile']);
        $name = trim(preg_replace('/\s+/', ' ', $v['name']));
        $start = (int) $v['time'];

        $appt = \Illuminate\Support\Facades\DB::transaction(function () use ($c, $v, $mobile, $name, $start) {
            Clinic::whereKey($c->id)->lockForUpdate()->first(); // one booking at a time per clinic, so two people can't take the same slot
            if (! in_array($start, self::openTimes($c, $v['date']), true)) {
                return null;
            }
            $busy = Appointment::where(['clinic_id' => $c->id, 'date' => $v['date']])->where('status', '!=', 'CANCELLED')
                ->where('start_min', '<=', $start)->get()->filter(fn ($a) => $a->start_min + $a->duration > $start)->pluck('chair_id')->all();
            $chair = Chair::where(['clinic_id' => $c->id, 'active' => true])->whereNotIn('id', $busy)->orderBy('id')->first();
            $patient = Patient::where(['clinic_id' => $c->id, 'mobile' => $mobile])->get()->first(fn ($p) => mb_strtolower($p->name) === mb_strtolower($name));
            $patient ??= Patient::create([
                'clinic_id' => $c->id, 'code' => PatientController::nextCode($c->id), 'name' => $name, 'mobile' => $mobile, 'medical_history' => [],
            ]);
            $doctor = User::where(['clinic_id' => $c->id, 'role' => 'DOCTOR', 'active' => true])->orderByDesc('is_owner')->value('id');
            return Appointment::create([
                'clinic_id' => $c->id, 'patient_id' => $patient->id, 'chair_id' => $chair->id, 'doctor_id' => $doctor,
                'date' => $v['date'], 'start_min' => $start, 'treatment' => ($v['reason'] ?? '') ?: 'Consultation',
                'status' => $c->booking_approval ? 'REQUESTED' : 'BOOKED', 'source' => 'ONLINE',
            ]);
        });
        if (! $appt) {
            return back()->withInput()->with('error', 'Sorry, someone just took that time. Please pick another one.');
        }
        Activity::logSystem($c->id, 'Online booking', $appt->patient->name.' · '.Fmt::showMobile($mobile).' · '.Fmt::date($appt->date).' '.Fmt::time($start).($c->booking_approval ? ' · waiting for confirmation' : ''));
        return redirect()->route('book.page', $c->booking_slug)->with('booked', [
            'when' => Fmt::date($appt->date).', '.Fmt::time($start),
            'name' => $appt->patient->name,
            'pending' => (bool) $c->booking_approval,
        ]);
    }
}
