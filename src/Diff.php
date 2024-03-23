<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Jfcherng\Diff\Differ;
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

    protected function getContents(): array {
        if ($this->toVersion->versionable->getVersionStrategy() === VersionStrategy::DIFF) {
            if ($this->toVersion->previousVersions()->get()->last()) {
                $newContents = json_decode($this->toVersion->previousVersions()->get()->last()->getRawOriginal()['contents'], true);
            } else {
                $newContents = $this->toVersion->versionable()->firstVersion()->get()->first()->toArray();
            };

            $oldContents = $this->fromVersion->contents;

            $versionsBeforeThis = $this->toVersion->previousVersions()->get();
            foreach ($versionsBeforeThis as $version) {
                if (! empty($version->contents)) {
                    $newContents = array_merge($newContents, $version->contents);
                }
            }

            $newContents = Arr::only($newContents, array_keys($oldContents));
        } else {
            $oldContents = $this->fromVersion->contents;
            $newContents = $this->toVersion->contents;
        }

        return [$oldContents, $newContents];
    }

    public function render(?string $renderer = null, array $differOptions = [], array $renderOptions = []): array
    {
        if (empty($differOptions)) {
            $differOptions = $this->differOptions;
        }

        if (empty($renderOptions)) {
            $renderOptions = $this->renderOptions;
        }

        list($oldContents, $newContents) = $this->getContents();

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

        foreach ($oldContents as $key => $value) {
            $createDiff($key, Arr::get($newContents, $key), Arr::get($oldContents, $key));
        }

        foreach (array_diff_key($oldContents, $newContents) as $key => $value) {
            $createDiff($key, null, $value);
        }

        return $diff;
    }

    public function getStatistics(array $differOptions = []): array
    {
        if (empty($differOptions)) {
            $differOptions = $this->differOptions;
        }

        list($oldContents, $newContents) = $this->getContents();

        $diffStats = new Collection;

        foreach ($oldContents as $key => $value) {
            if ($newContents[$key] !== $oldContents[$key]) {
                $diffStats->push(
                    (new Differ(
                        explode("\n", $newContents[$key]),
                        explode("\n", $oldContents[$key]),
                    ))->getStatistics()
                );
            }
        }

        return [
            'inserted' => $diffStats->sum('inserted'),
            'deleted' => $diffStats->sum('deleted'),
            'unmodified' => $diffStats->sum('unmodified'),
            'changedRatio' => $diffStats->sum('changedRatio'),
        ];
    }
}
