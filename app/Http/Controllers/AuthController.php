<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Chair;
use App\Models\Clinic;
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
        $mobile = Fmt::mobile($r->input('mobile'));
        if (! $mobile) {
            return back()->withInput()->with('error', 'Enter your 10-digit mobile number.');
        }
        $user = User::where('mobile', $mobile)->first();
        if (! $user || ! password_verify((string) $r->input('password'), $user->password)) {
            return back()->withInput()->with('error', 'Mobile number or password is wrong.');
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
        $r->merge(['receptionists' => array_values(array_filter($r->input('receptionists', []), fn ($x) => trim(($x['name'] ?? '').($x['mobile'] ?? '')) !== ''))]);
        $person = fn ($p) => [
            "$p.name" => 'required|string|min:2|max:100',
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
            ...$person('admin'),
            ...$person('doctor'),
            'doctor.qualification' => 'required|string|min:2|max:100',
            'doctor.reg_no' => 'required|string|min:2|max:50',
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
        $mobiles = array_map(fn ($p) => Fmt::mobile($p[1]['mobile']), $people);
        if (count(array_unique($mobiles)) !== count($mobiles)) {
            return back()->withInput()->with('error', 'Each person needs a different mobile number.');
        }
        if ($taken = User::whereIn('mobile', $mobiles)->value('mobile')) {
            return back()->withInput()->with('error', "$taken is already registered. Use another number or sign in.");
        }

        $admin = DB::transaction(function () use ($v, $people) {
            $c = $v['clinic'];
            $clinic = Clinic::create([
                'name' => $c['name'], 'address' => $c['address'], 'city' => $c['city'], 'phone' => $c['phone'],
                'gstin' => $c['gstin'] ? strtoupper($c['gstin']) : null, 'patient_prefix' => $c['prefix'],
            ]);
            for ($i = 1; $i <= $c['chairs']; $i++) {
                Chair::create(['clinic_id' => $clinic->id, 'name' => "Chair $i"]);
            }
            $admin = null;
            foreach ($people as [$role, $p]) {
                $u = User::create([
                    'clinic_id' => $clinic->id, 'name' => $p['name'], 'mobile' => Fmt::mobile($p['mobile']), 'password' => $p['password'],
                    'role' => $role, 'photo' => $p['photo'] ?? null,
                    ...($role === 'DOCTOR' ? ['qualification' => $p['qualification'], 'reg_no' => $p['reg_no'], 'email' => $p['email'] ?? null, 'specialisation' => $p['specialisation'] ?? null] : []),
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
