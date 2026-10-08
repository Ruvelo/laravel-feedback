<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ruvelo\Feedback\Feedback;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin area and every manager action: the `feedback-manage`
 * gate, which lets nobody in until you define it. Guests are sent to the
 * app's login page when it has one, never to a 500.
 */
class AuthorizeManaging
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Feedback::canManage($request->user())) {
            return $next($request);
        }

        if ($request->user() === null) {
            abort_if($request->expectsJson(), 401);

            if (Route::has('login')) {
                return redirect()->guest(route('login'));
            }
        }

        abort(403);
    }
}
