<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Support;

use Illuminate\Support\Str;

/**
 * Plain text from Markdown, for excerpts, feeds and notifications.
 *
 * @internal
 */
final class Text
{
    public static function plain(?string $markdown): string
    {
        $text = (string) $markdown;
        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}(?:#{1,6}|>|[-*+]|\d+[.)])\s+/m', '', $text) ?? $text;
        $text = str_replace(['**', '__', '`', '~~'], '', $text);
        $text = preg_replace('/(?<![\w*])[*_](?=\S)|(?<=\S)[*_](?![\w*])/u', '', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    public static function excerpt(?string $markdown, int $length = 180): string
    {
        return Str::limit(self::plain($markdown), $length, '…', preserveWords: true);
    }

    /**
     * The words to search for: lower-cased, unique, longest first, short
     * filler words dropped when there are better ones.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique($words));
        $meaningful = array_values(array_filter($words, fn (string $word): bool => mb_strlen($word) > 2 && ! in_array($word, self::STOP_WORDS, true)));

        $words = $meaningful !== [] ? $meaningful : $words;
        usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_slice($words, 0, 8);
    }

    private const STOP_WORDS = ['the', 'and', 'for', 'with', 'add', 'can', 'you', 'please', 'would', 'like', 'able', 'allow', 'option', 'support', 'feature', 'from', 'into', 'our', 'your', 'when', 'should', 'have', 'more', 'all', 'not', 'are', 'way', 'let', 'use', 'get', 'new'];
}
