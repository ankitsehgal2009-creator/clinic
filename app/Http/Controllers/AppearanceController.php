<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/** Each person's own look: colour theme, light or dark, menu style, text size and corners. */
class AppearanceController extends Controller
{
    public function show()
    {
        return view('appearance', ['me' => $this->me()]);
    }

    public function save(Request $r)
    {
        $me = $this->me();
        $v = $r->validate([
            'theme' => 'required|in:'.implode(',', array_keys(User::THEMES)),
            'mode' => 'required|in:'.implode(',', array_keys(User::MODES)),
            'menu' => 'required|in:'.implode(',', array_keys(User::MENUS)),
            'size' => 'required|in:'.implode(',', array_keys(User::SIZES)),
            'corners' => 'required|in:'.implode(',', array_keys(User::CORNERS)),
        ]);
        $me->update(['prefs' => $v]);
        return response()->json(['ok' => true]);
    }
}
