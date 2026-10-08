<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;

class RoadmapController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('feedback::roadmap', [
            'columns' => Feedback::roadmap($this->viewer($request)),
        ]);
    }
}
