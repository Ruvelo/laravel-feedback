<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers\Manage;

use Illuminate\Contracts\View\View;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Http\Controllers\Controller;

class StatsController extends Controller
{
    public function __invoke(): View
    {
        return view('feedback::manage.stats', ['stats' => Feedback::stats()]);
    }
}
