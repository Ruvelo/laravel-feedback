<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Ruvelo\Feedback\Models\FeatureRequest;
use Ruvelo\Feedback\Models\Vote;
use Ruvelo\Feedback\Support\People;

/**
 * Tells a request's author and voters that its status changed. Nobody is
 * told twice, nobody hears about their own change, and only notifiable
 * models are told.
 *
 * @internal
 */
final class Notifier
{
    public static function statusChanged(FeatureRequest $request, ?string $from, string $to, ?string $note, ?Model $by): void
    {
        if (! config('feedback.notifications.enabled', true)) {
            return;
        }

        $statuses = config('feedback.notifications.statuses');
        if (is_array($statuses) && ! in_array($to, $statuses, true)) {
            return;
        }

        $recipients = self::audience($request, $by);

        if ($recipients !== []) {
            Notification::send($recipients, new RequestStatusChanged($request, $from, $to, $note));
        }
    }

    /**
     * The author and every voter, minus $except.
     *
     * @return list<Model>
     */
    public static function audience(FeatureRequest $request, ?Model $except = null): array
    {
        $people = [];
        $skip = $except !== null ? People::key($except) : null;

        $add = function (?Model $person) use (&$people, $skip): void {
            if ($person === null || ! method_exists($person, 'routeNotificationFor')) {
                return;
            }
            $key = People::key($person);
            if ($key !== $skip) {
                $people[$key] = $person;
            }
        };

        $add($request->author);

        $request->votes()->with('voter')->orderBy('id')->each(function (Vote $vote) use ($add): void {
            $add($vote->voter);
        });

        return array_values($people);
    }
}
