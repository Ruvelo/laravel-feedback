<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Models\FeatureRequest;

class VoteController extends Controller
{
    /**
     * Vote, or take the vote back.
     */
    public function __invoke(Request $request, FeatureRequest $featureRequest): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        $feature = $featureRequest;

        abort_unless($feature->isApproved() || $feature->isAuthoredBy($user) || Feedback::canManage($user), 404);

        Feedback::authorize('feedback-vote', $request->user(), $feature);

        // An explicit `vote=1|0` makes retries safe; without it, toggle.
        $wanted = $request->has('vote') ? $request->boolean('vote') : ! $feature->hasVoter($user);
        $wanted ? Feedback::vote($feature, $user) : Feedback::unvote($feature, $user);

        $feature->refresh();

        if ($request->expectsJson()) {
            return response()->json(['voted' => $wanted, 'votes' => $feature->votes_count]);
        }

        return back(fallback: $feature->url())->with('feedback.status', $wanted ? 'Thanks for your vote.' : 'Your vote was taken back.');
    }
}
