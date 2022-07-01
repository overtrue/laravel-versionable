<?php

namespace Overtrue\LaravelVersionable;

use Jfcherng\Diff\DiffHelper;

/**
 * @see https://github.com/jfcherng/php-diff#example
 */
class Diff
{
    public function __construct(
        public Version $fromVersion,
        public Version $toVersion,
        public array $differOptions = [],
        public array $renderOptions = []
    ) {
    }

    public function toArray(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render(null, $differOptions, $renderOptions);
    }

    public function toText(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('Unified', $differOptions, $renderOptions);
    }

    public function toJsonText(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('JsonText', $differOptions, $renderOptions);
    }

    public function toContextText(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('Context', $differOptions, $renderOptions);
    }

    public function toHtml(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('Combined', $differOptions, $renderOptions);
    }

    public function toInlineHtml(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('Inline', $differOptions, $renderOptions);
    }

    public function toJsonHtml(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('JsonHtml', $differOptions, $renderOptions);
    }

    public function toSideBySideHtml(array $differOptions = [], array $renderOptions = []): array
    {
        return $this->render('SideBySide', $differOptions, $renderOptions);
    }

    public function render(string $renderer = null, array $differOptions = [], array $renderOptions = []): array
    {
        if (empty($differOptions)) {
            $differOptions = $this->differOptions;
        }

        if (empty($renderOptions)) {
            $renderOptions = $this->renderOptions;
        }

        $fromContents = $this->fromVersion->contents;
        $toContents = $this->toVersion->contents;

        $diff = [];
        $createDiff = function ($key, $old, $new) use (&$diff, $renderer, $differOptions, $renderOptions) {
            if ($renderer) {
                $old = is_string($old) ? $old : json_encode($old);
                $new = is_string($new) ? $new : json_encode($new);
                $diff[$key] = str_replace('\n No newline at end of file', '', DiffHelper::calculate($old, $new, $renderer, $differOptions, $renderOptions));
            } else {
                $diff[$key] = compact('old', 'new');
            }
        };

        foreach (array_diff_assoc($fromContents, $toContents) as $key => $value) {
            $createDiff($key, $toContents[$key] ?? null, $value);
        }

        foreach (array_diff_key($fromContents, $toContents) as $key => $value) {
            $createDiff($key, null, $value);
        }

        return $diff;
    }
}
