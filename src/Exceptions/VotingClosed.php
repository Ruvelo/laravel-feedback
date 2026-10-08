<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Exceptions;

use Ruvelo\Feedback\Models\FeatureRequest;

/**
 * The request is merged, waiting for approval, or in one of the
 * feedback.locked_statuses (shipped and closed by default).
 */
class VotingClosed extends FeedbackException
{
    public function __construct(public readonly FeatureRequest $request)
    {
        parent::__construct($request->isMerged()
            ? 'This request was merged into another one. Vote there instead.'
            : 'Voting is closed on this request.');
    }

    public function status(): int
    {
        return 409;
    }

    public function field(): string
    {
        return 'vote';
    }
}
