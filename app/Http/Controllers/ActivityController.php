<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $r)
    {
        $me = $this->me();
        $who = $r->query('user');
        return view('activity', [
            'rows' => Activity::with('user')->where('clinic_id', $me->clinic_id)->when($who, fn ($q) => $q->where('user_id', $who))->latest('id')->limit(300)->get(),
            'people' => User::where('clinic_id', $me->clinic_id)->orderBy('name')->get(['id', 'name']),
            'who' => $who,
        ]);
    }
}
