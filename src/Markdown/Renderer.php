<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Markdown to safe HTML. Raw HTML is always escaped and javascript:-style
 * links are dropped. Text from the public (requests, comments) gets
 * rel="nofollow ugc" links and images turned into links; changelog
 * entries, written by managers, keep their images.
 *
 * Each pipeline is built once and reused.
 */
class Renderer
{
    /** @var array<string, MarkdownConverter> */
    private array $converters = [];

    public function render(string $markdown, bool $trusted = false): string
    {
        $key = $trusted ? 'trusted' : 'public';

        return (string) ($this->converters[$key] ??= $this->build($trusted))->convert($markdown);
    }

    /**
     * Rebuild the pipelines on next use, e.g. after changing the config.
     */
    public function reset(): void
    {
        $this->converters = [];
    }

    private function build(bool $trusted): MarkdownConverter
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        $environment = new Environment(array_replace_recursive([
            'external_link' => [
                'internal_hosts' => is_string($host) ? [$host] : [],
                'nofollow' => $trusted ? '' : 'external',
                'noopener' => 'external',
                'noreferrer' => '',
            ],
            'max_nesting_level' => 20,
        ], (array) config('feedback.markdown.options', []), [
            // Not configurable: requests and comments come from anyone.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);

        foreach ((array) config('feedback.markdown.extensions', []) as $extension) {
            /** @var ExtensionInterface $extension */
            $extension = is_string($extension) ? app($extension) : $extension;
            $environment->addExtension($extension);
        }

        if (! $trusted) {
            $environment->addRenderer(Image::class, new ImageAsLinkRenderer, 10);
        }

        return new MarkdownConverter($environment);
    }
}
