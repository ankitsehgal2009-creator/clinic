<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Signed-in, still-active user who may open the given page; otherwise sign out or show "no access". */
class EnsurePage
{
    public function handle(Request $request, Closure $next, ?string $page = null)
    {
        $user = $request->user();
        if (! $user->active) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->with('error', 'Your account is turned off. Ask the clinic admin to turn it on.');
        }
        if ($page && ! $user->canOpen($page)) {
            if ($request->expectsJson()) {
                abort(403, "You can't open this page.");
            }
            return redirect()->route('no-access');
        }
        return $next($request);
    }
}
