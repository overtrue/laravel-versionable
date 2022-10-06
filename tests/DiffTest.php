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
                'content' => DiffHelper::calculate('version1 content', 'version2 content', 'SideBySide'),
                'user_id' => DiffHelper::calculate(json_encode(null), json_encode(123), 'SideBySide'),
            ],
            (new Diff($new, $old))->toSideBySideHtml()
        );
    }
}
