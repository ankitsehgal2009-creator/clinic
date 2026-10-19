<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Chair;
use App\Models\Clinic;
use App\Models\TreatmentPrice;
use App\Models\User;
use App\Support\Fmt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $id = trim((string) $r->input('login'));
        if ($id === '') {
            return back()->withInput()->with('error', 'Enter your username or mobile number.');
        }
        $user = User::where('username', strtolower($id))->first();
        if (! $user && ($mobile = Fmt::mobile($id))) {
            $same = User::where('mobile', $mobile)->get();
            if ($same->count() > 1) {
                return back()->withInput()->with('error', 'More than one person uses this mobile number. Sign in with your username instead.');
            }
            $user = $same->first();
        }
        if (! $user || ! password_verify((string) $r->input('password'), $user->password)) {
            return back()->withInput()->with('error', 'Username, mobile number or password is wrong.');
        }
        if (! $user->active) {
            return back()->withInput()->with('error', 'Your account is turned off. Ask the clinic admin to turn it on.');
        }
        Auth::login($user, true);
        $r->session()->regenerate();
        Activity::log($user, 'Signed in');
        return redirect($user->homeUrl());
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return redirect('/login');
    }

    public function showSetup()
    {
        return view('auth.setup');
    }

    public function setup(Request $r)
    {
        // Drop receptionist rows left completely empty.
        $r->merge(['receptionists' => array_values(array_filter($r->input('receptionists', []), fn ($x) => trim(($x['name'] ?? '').($x['mobile'] ?? '').($x['username'] ?? '')) !== ''))]);
        $person = fn ($p) => [
            "$p.name" => 'required|string|min:2|max:100',
            "$p.username" => ['required', self::usernameRule()],
            "$p.mobile" => ['required', self::mobileRule()],
            "$p.password" => 'required|string|min:6',
            "$p.photo" => 'nullable|string|max:200000',
        ];
        $v = $r->validate([
            'clinic.name' => 'required|string|min:2|max:150',
            'clinic.address' => 'required|string|min:3|max:250',
            'clinic.city' => 'required|string|min:2|max:100',
            'clinic.phone' => 'required|string|min:6|max:30',
            'clinic.gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'clinic.prefix' => 'required|string|max:6',
            'clinic.chairs' => 'required|integer|min:1|max:20',
            'clinic.logo' => ['nullable', 'string', 'max:400000', self::imageRule()],
            ...$person('admin'),
            ...$person('doctor'),
            'doctor.qualification' => 'nullable|string|max:100',
            'doctor.reg_no' => 'nullable|string|max:50',
            'doctor.email' => 'nullable|email',
            'doctor.specialisation' => 'nullable|string|max:60',
            ...$person('receptionists.*'),
        ], [
            'clinic.gstin.regex' => 'GSTIN should be 15 characters, like 27AAKFS4821M1Z6.',
            '*.password.min' => 'Passwords need at least 6 characters.',
            'receptionists.*.password.min' => 'Passwords need at least 6 characters.',
        ]);

        $people = [['ADMIN', $v['admin']], ['DOCTOR', $v['doctor']]];
        foreach ($v['receptionists'] ?? [] as $rec) {
            $people[] = ['RECEPTIONIST', $rec];
        }
        // Mobile numbers may repeat (a doctor can also be the admin); usernames must not.
        $names = array_map(fn ($p) => User::cleanUsername($p[1]['username']), $people);
        if (count(array_unique($names)) !== count($names)) {
            return back()->withInput()->with('error', 'Each person needs a different username.');
        }
        if ($taken = User::whereIn('username', $names)->value('username')) {
            return back()->withInput()->with('error', "The username \"$taken\" is already taken. Choose another one.");
        }

        $admin = DB::transaction(function () use ($v, $people) {
            $c = $v['clinic'];
            $clinic = Clinic::create([
                'name' => $c['name'], 'address' => $c['address'], 'city' => $c['city'], 'phone' => $c['phone'],
                'gstin' => $c['gstin'] ? strtoupper($c['gstin']) : null, 'patient_prefix' => $c['prefix'],
                'invoice_prefix' => TreatmentPrice::prefixFrom($c['prefix']), 'logo' => $c['logo'] ?? null,
            ]);
            TreatmentPrice::seedDefaults($clinic->id);
            \App\Support\Rx::seedTemplates($clinic->id);
            $clinic->update(['booking_slug' => BookingController::freeSlug($clinic->name)]);
            for ($i = 1; $i <= $c['chairs']; $i++) {
                Chair::create(['clinic_id' => $clinic->id, 'name' => "Chair $i"]);
            }
            $admin = null;
            foreach ($people as [$role, $p]) {
                $u = User::create([
                    'clinic_id' => $clinic->id, 'name' => $p['name'], 'username' => User::cleanUsername($p['username']), 'mobile' => Fmt::mobile($p['mobile']), 'password' => $p['password'],
                    'role' => $role, 'photo' => $p['photo'] ?? null, 'is_owner' => $role === 'DOCTOR',
                    ...($role === 'DOCTOR' ? ['qualification' => $p['qualification'] ?? null, 'reg_no' => $p['reg_no'] ?? null, 'email' => $p['email'] ?? null, 'specialisation' => $p['specialisation'] ?? null] : []),
                ]);
                $admin ??= $u;
            }
            return $admin;
        });

        Activity::log($admin, 'Completed clinic setup', $v['clinic']['name'].' · '.count($v['receptionists'] ?? []).' receptionist(s)');
        Auth::login($admin, true);
        $r->session()->regenerate();
        return redirect('/dashboard');
    }
}
