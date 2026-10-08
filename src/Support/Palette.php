<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Support;

/**
 * Statuses and changelog labels, as configured: keys, labels and colours.
 *
 * A colour is a palette name (accent, lilac, success, warning, danger,
 * neutral), which follows light and dark mode, or a hex colour.
 *
 * @internal
 */
final class Palette
{
    public const NAMES = ['accent', 'lilac', 'success', 'warning', 'danger', 'neutral'];

    /**
     * @return array<string, string> key => label
     */
    public static function statuses(): array
    {
        return self::labels('feedback.statuses');
    }

    /**
     * @return array<string, string> key => label
     */
    public static function changelogLabels(): array
    {
        return self::labels('feedback.changelog.labels');
    }

    public static function statusLabel(string $status): string
    {
        return self::statuses()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function changelogLabel(string $label): string
    {
        return self::changelogLabels()[$label] ?? ucfirst($label);
    }

    /**
     * Class and inline style for a chip in this status's colour.
     *
     * @return array{class: string, style: string}
     */
    public static function statusChip(string $status): array
    {
        return self::chip('feedback.statuses', $status);
    }

    /**
     * @return array{class: string, style: string}
     */
    public static function labelChip(string $label): array
    {
        return self::chip('feedback.changelog.labels', $label);
    }

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return self::known((array) config('feedback.open_statuses', ['under_review', 'planned', 'in_progress']));
    }

    /**
     * @return list<string>
     */
    public static function lockedStatuses(): array
    {
        return self::known((array) config('feedback.locked_statuses', ['shipped', 'closed']));
    }

    /**
     * @return list<string>
     */
    public static function roadmapStatuses(): array
    {
        return self::known((array) config('feedback.roadmap.statuses', ['planned', 'in_progress', 'shipped']));
    }

    /**
     * @param  array<mixed>  $keys
     * @return list<string>
     */
    private static function known(array $keys): array
    {
        $statuses = self::statuses();

        return array_values(array_filter($keys, fn (mixed $key): bool => is_string($key) && isset($statuses[$key])));
    }

    /**
     * @return array<string, string>
     */
    private static function labels(string $key): array
    {
        $labels = [];

        foreach ((array) config($key, []) as $name => $definition) {
            $label = is_array($definition) ? ($definition['label'] ?? null) : $definition;
            $labels[(string) $name] = is_string($label) && $label !== '' ? $label : ucfirst(str_replace('_', ' ', (string) $name));
        }

        return $labels;
    }

    /**
     * @return array{class: string, style: string}
     */
    private static function chip(string $config, string $key): array
    {
        $definition = config($config.'.'.$key);
        $color = is_array($definition) && is_string($definition['color'] ?? null) ? strtolower($definition['color']) : 'neutral';

        if (in_array($color, self::NAMES, true)) {
            return ['class' => 'fb-tone-'.$color, 'style' => ''];
        }

        if (preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/', $color) === 1) {
            return ['class' => 'fb-tone-custom', 'style' => '--fb-tone: '.$color.';'];
        }

        return ['class' => 'fb-tone-neutral', 'style' => ''];
    }
}
