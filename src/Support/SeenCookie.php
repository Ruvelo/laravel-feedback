<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;

/**
 * When a guest last looked at the changelog, kept in a year-long cookie.
 * Signed-in people's time is stored in the database instead.
 *
 * @internal
 */
final class SeenCookie
{
    public const NAME = 'feedback_seen';

    public static function get(Request $request): ?Carbon
    {
        $value = $request->cookie(self::NAME);

        return is_string($value) && ctype_digit($value) ? Carbon::createFromTimestamp((int) $value) : null;
    }

    public static function queue(?CarbonInterface $at = null): void
    {
        Cookie::queue(self::NAME, (string) ($at ?? Carbon::now())->getTimestamp(), 60 * 24 * 365);
    }
}
