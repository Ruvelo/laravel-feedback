<?php

declare(strict_types=1);

namespace Ruvelo\Feedback\Tests\Unit;

use Illuminate\Http\Request;
use Ruvelo\Feedback\Feedback;
use Ruvelo\Feedback\Support\Board;
use Ruvelo\Feedback\Support\Palette;
use Ruvelo\Feedback\Support\People;
use Ruvelo\Feedback\Support\Text;
use Ruvelo\Feedback\Tests\TestCase;

class SupportTest extends TestCase
{
    public function test_plain_text_and_excerpts(): void
    {
        $this->assertSame('Title Some bold and a link. item', Text::plain("# Title\n\nSome **bold** and [a link](https://x.test).\n\n- item\n\n```php\ncode();\n```"));
        $this->assertSame('Short', Text::excerpt('Short'));
        $this->assertSame('One two…', Text::excerpt('One two. Three four five six', 10));
    }

    public function test_search_words_drop_filler(): void
    {
        $this->assertSame(['quickbooks', 'invoices', 'sync'], Text::words('Please add sync invoices with QuickBooks!'));
        $this->assertSame(['ui'], Text::words('UI'));
        $this->assertSame([], Text::words(' ,. '));
    }

    public function test_initials_and_names(): void
    {
        $this->assertSame('MO', People::initials('Maya Okafor'));
        $this->assertSame('IL', People::initials('inès laurent-dupont'));
        $this->assertSame('K', People::initials('Kenji'));
        $this->assertSame('?', People::initials('  '));
        $this->assertSame('Someone', People::name(null));
        $this->assertSame('Ada Lovelace', People::name($this->user()));
    }

    public function test_status_and_label_colours(): void
    {
        $this->assertSame(['class' => 'fb-tone-success', 'style' => ''], Palette::statusChip('shipped'));
        $this->assertSame(['class' => 'fb-tone-neutral', 'style' => ''], Palette::statusChip('unknown'));

        config(['feedback.statuses.beta' => ['label' => 'Beta', 'color' => '#0F766E'], 'feedback.statuses.bad' => ['color' => 'url(evil)']]);
        $this->assertSame(['class' => 'fb-tone-custom', 'style' => '--fb-tone: #0f766e;'], Palette::statusChip('beta'));
        $this->assertSame(['class' => 'fb-tone-neutral', 'style' => ''], Palette::statusChip('bad'), 'Only palette names and hex colours reach the page.');
        $this->assertSame('Bad', Palette::statusLabel('bad'));

        config(['feedback.open_statuses' => ['planned', 'nope']]);
        $this->assertSame(['planned'], Palette::openStatuses());
        $this->assertSame('Improved', Palette::changelogLabel('improved'));
    }

    public function test_board_filters_from_the_query_string(): void
    {
        $board = Board::fromRequest(Request::create('/feedback', 'GET', ['status' => 'planned', 'category' => 'Billing!', 'tag' => 'enterprise', 'q' => '  dark ', 'sort' => 'new']));

        $this->assertSame(['planned', null, 'enterprise', 'dark', 'new'], [$board->status, $board->category, $board->tag, $board->q, $board->sort]);
        $this->assertSame(['status' => 'planned', 'tag' => 'enterprise', 'q' => 'dark', 'sort' => 'new'], $board->with());
        $this->assertSame(['tag' => 'enterprise', 'q' => 'dark', 'sort' => 'new'], $board->with(['status' => 'open']));
        $this->assertTrue($board->isFiltered());
        $this->assertFalse(Board::fromRequest(Request::create('/feedback'))->isFiltered());
    }

    public function test_markdown_is_safe(): void
    {
        $html = Feedback::render('<b>hi</b> [x](javascript:alert(1)) ![i](https://t.test/p.png) [ok](https://example.org)');

        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('rel="nofollow noopener"', $html);

        $this->assertStringContainsString('<img src="https://t.test/p.png"', Feedback::render('![i](https://t.test/p.png)', trusted: true));
        $this->assertStringContainsString('&lt;script&gt;', Feedback::render('<script>x</script>', trusted: true));
    }
}
