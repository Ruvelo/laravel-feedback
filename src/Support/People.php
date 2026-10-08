<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Names and initials for whoever posts, votes and comments: any model.
 *
 * @internal
 */
final class People
{
    public static function name(?Model $person, string $fallback = 'Someone'): string
    {
        if ($person === null) {
            return $fallback;
        }

        $attribute = (string) config('feedback.name_attribute', 'name');
        $name = $person->getAttribute($attribute) ?? $person->getAttribute('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : $fallback;
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-_.]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    /**
     * A stable key for a model, for de-duplicating people.
     */
    public static function key(Model $person): string
    {
        return $person->getMorphClass().':'.$person->getKey();
    }
}
