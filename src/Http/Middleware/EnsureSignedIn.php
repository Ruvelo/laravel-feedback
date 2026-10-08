<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards posting, voting and commenting. Guests get a 401 from JSON
 * calls, or the app's login page when it has one. A fresh app has no
 * `login` route, and Laravel's own `auth` middleware fails with a 500
 * there; this answers 403 instead.
 */
class EnsureSignedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 401, 'Sign in to take part.');

        if (Route::has('login')) {
            // Come back to the page they were on, not to a POST-only URL.
            if ($request->hasSession()) {
                $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : url()->previous());
            }

            return redirect()->to(route('login'));
        }

        abort(403, 'Sign in to take part.');
    }
}
