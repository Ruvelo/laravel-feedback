<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The signed-in user, when it's an Eloquent model.
     */
    protected function viewer(Request $request): ?Model
    {
        $user = $request->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * The signed-in user. Only called behind EnsureSignedIn.
     */
    protected function user(Request $request): Model
    {
        $user = $this->viewer($request);
        abort_if($user === null, 401);

        return $user;
    }

    /**
     * Whether the hidden field people never see was filled in: a bot.
     */
    protected function caughtInHoneypot(Request $request): bool
    {
        $field = config('feedback.honeypot', 'website');

        return is_string($field) && $field !== '' && filled($request->input($field));
    }
}
