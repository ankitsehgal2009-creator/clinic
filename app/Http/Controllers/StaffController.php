<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Support\Fmt;
use App\Support\Pages;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        $me = $this->me();
        $staff = User::where('clinic_id', $me->clinic_id)->orderByRaw("CASE role WHEN 'ADMIN' THEN 0 WHEN 'DOCTOR' THEN 1 WHEN 'RECEPTIONIST' THEN 2 ELSE 3 END")->orderBy('name')->get();
        return view('staff.index', ['staff' => $staff, 'others' => $staff->where('role', '!=', 'ADMIN')]);
    }

    public function store(Request $r)
    {
        $me = $this->me();
        abort_unless($me->isAdmin(), 403, 'Only the admin can add staff.');
        $v = $r->validate([
            'name' => 'required|string|min:2|max:100',
            'mobile' => ['required', self::mobileRule()],
            'role' => 'required|in:DOCTOR,RECEPTIONIST,ASSISTANT',
            'password' => 'required|string|min:6',
            'qualification' => 'nullable|string|max:100',
            'reg_no' => 'nullable|string|max:50',
            'photo' => 'nullable|string|max:200000',
        ], ['password.min' => 'Passwords need at least 6 characters.']);
        $mobile = Fmt::mobile($v['mobile']);
        if (User::where('mobile', $mobile)->exists()) {
            return back()->withInput()->with('error', 'That mobile number already has an account.');
        }
        User::create(['clinic_id' => $me->clinic_id, ...$v, 'mobile' => $mobile]);
        Activity::log($me, 'Added staff member', $v['name'].' · '.Pages::ROLE_LABEL[$v['role']]);
        return back()->with('ok', "{$v['name']} can now sign in with $mobile.");
    }

    public function active(Request $r, User $user)
    {
        $me = $this->me();
        $this->own($user);
        abort_unless($me->isAdmin() || $me->role === 'DOCTOR', 403, 'Only the admin or a doctor can turn staff on or off.');
        abort_if($user->id === $me->id, 403, "You can't turn off your own account.");
        abort_if($user->isAdmin() && ! $me->isAdmin(), 403, 'Only the admin can change the admin account.');
        $user->update(['active' => $r->boolean('active')]);
        Activity::log($me, $user->active ? 'Enabled staff member' : 'Disabled staff member', $user->name);
        return $r->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function pages(Request $r, User $user)
    {
        $me = $this->me();
        $this->own($user);
        abort_unless($me->isAdmin(), 403, 'Only the admin can choose pages.');
        $keys = array_values(array_intersect(array_keys(Pages::PAGES), (array) $r->input('pages', [])));
        $user->update(['pages' => json_encode($keys)]);
        $labels = array_map(fn ($k) => Pages::PAGES[$k]['label'], $keys);
        Activity::log($me, 'Changed page access', "$user->name: ".($labels ? implode(', ', $labels) : 'no pages'));
        return response()->json(['ok' => true]);
    }
}
