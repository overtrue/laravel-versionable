<?php

namespace Tests;

use Jfcherng\Diff\DiffHelper;
use Overtrue\LaravelVersionable\Diff;
use Overtrue\LaravelVersionable\Version;
use PHPUnit\Framework\TestCase;

class DiffTest extends TestCase
{
    public function test_diff_to_array()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => ['old' => 'version1', 'new' => 'version1'],
                'content' => ['old' => 'version1 content', 'new' => 'version2 content'],
                'user_id' => ['old' => null, 'new' => 123],
            ],
            (new Diff($new, $old))->toArray()
        );

        // reversed diff
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'user_id' => 123]]);
        $this->assertSame(
            [
                'title' => ['old' => 'version1', 'new' => 'version1'],
                'content' => ['old' => null, 'new' => 'version1 content'],
            ],
            (new Diff($old, $new))->toArray()
        );
    }

    public function test_diff_to_context_text()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'Context'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'Context'),
            ],
            (new Diff($new, $old))->toContextText()
        );
    }

    public function test_diff_to_text()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'Unified'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'Unified'),
            ],
            (new Diff($new, $old))->toText()
        );
    }

    public function test_diff_to_json_text()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'JsonText'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'JsonText'),
            ],
            (new Diff($new, $old))->toJsonText()
        );
    }

    public function test_diff_to_html()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'Combined'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'Combined'),
            ],
            (new Diff($new, $old))->toHtml()
        );
    }

    public function test_diff_to_inline_html()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'Inline'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'Inline'),
            ],
            (new Diff($new, $old))->toInlineHtml()
        );
    }

    public function test_diff_to_json_html()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '[]',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'JsonHtml'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'JsonHtml'),
            ],
            (new Diff($new, $old))->toJsonHtml()
        );
    }

    public function test_diff_to_side_by_side_html()
    {
        $old = new Version(['contents' => ['title' => 'version1', 'content' => 'version1 content']]);
        $new = new Version(['contents' => ['title' => 'version1', 'content' => 'version2 content', 'user_id' => 123]]);

        $this->assertSame(
            [
                'title' => '',
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'SideBySide'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'SideBySide'),
            ],
            (new Diff($new, $old))->toSideBySideHtml()
        );
    }

    /**
     * @test
     */
    public function it_can_return_the_diff_statistics()
    {
        $old = new Version(['contents' => ['title' => 'example title', 'content' => 'example content']]);

        // we are modifying everything
        $new = new Version(['contents' => ['title' => 'changing the title', 'content' => 'changing the content']]);

        $stats = (new Diff($new, $old))->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('inserted', $stats);
        $this->assertArrayHasKey('deleted', $stats);
        $this->assertArrayHasKey('unmodified', $stats);

        $this->assertEquals(2, $stats['inserted']); // two new lines inserted
        $this->assertEquals(2, $stats['deleted']); // two lines deleted
        $this->assertEquals(0, $stats['unmodified']); // modified everything
    }

    /**
     * @test
     */
    public function it_can_return_correct_statistics_for_new_line_insertions()
    {
        $old = new Version(['contents' => ['title' => 'example title', 'content' => 'example content']]);

        // we are adding two new lines
        $new = new Version(['contents' => ['content' => "example content\n adding new line \n another new line"]]);

        $stats = (new Diff($new, $old))->getStatistics();

        $this->assertEquals(2, $stats['inserted']); // two new lines inserted
        $this->assertEquals(0, $stats['deleted']); // no deletions
        $this->assertEquals(1, $stats['unmodified']); // one line unmodified
    }

    /**
     * @test
     */
    public function it_can_return_correct_statistics_for_deleted_lines()
    {
        $old = new Version(['contents' => ['title' => 'example title', 'content' => "example content\n adding new line \n another new line"]]);

        // we are removing last two lines
        $new = new Version(['contents' => ['content' => 'example content']]);

        $stats = (new Diff($new, $old))->getStatistics();

        $this->assertEquals(0, $stats['inserted']); // no insertions
        $this->assertEquals(2, $stats['deleted']); // two lines deleted
        $this->assertEquals(1, $stats['unmodified']); // one line unmodified
    }

    /**
     * @test
     */
    public function it_can_return_correct_statistics_for_unmodified_lines()
    {
        $old = new Version(['contents' => ['title' => 'example title', 'content' => "example content\n adding new line \n another new line"]]);

        // we are just removing the last line
        $new = new Version(['contents' => ['content' => "example content\n adding new line "]]);

        $stats = (new Diff($new, $old))->getStatistics();

        $this->assertEquals(0, $stats['inserted']); // no insertions
        $this->assertEquals(1, $stats['deleted']); // one line deleted
        $this->assertEquals(2, $stats['unmodified']); // two lines unmodified
    }

    public function test_diff_nested_array_to_array()
    {
        $oldContent = [
            'a' => 'nested content version 1',
            'b' => [
                -44.061269,
                'lorem',
                'ipsum dolor sit amet',
            ],
            'c' => [
                'c1' => [
                    'c11' => -44.061269,
                    'c12' => 42.061269,
                ],
                'c2' => 'lorem',
                'c3' => 'ipsum dolor sit amet',
            ],
        ];
        $old = new Version([
            'contents' => [
                'title' => 'version1',
                'content' => $oldContent,
            ],
        ]);

        $newContent = [
            'a' => 'nested content version 2',
            'c' => [
                'c1' => [
                    'c11' => -46.061269,
                    'c12' => 142.061269,
                ],
                'c2' => 'dolor',
                'c3' => 'sit amet',
            ],
        ];
        $new = new Version([
            'contents' => [
                'title' => 'version2',
                'content' => $newContent,
                'user_id' => 123,
            ],
        ]);

        $this->assertSame(
            [
                'title' => [
                    'old' => 'version1',
                    'new' => 'version2',
                ],
                'content' => [
                    'old' => $oldContent,
                    'new' => $newContent,
                ],
                'user_id' => [
                    'old' => null,
                    'new' => 123,
                ],
            ],
            (new Diff($new, $old))->toArray()
        );
    }
}
