<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * @property \Illuminate\Database\Eloquent\Collection<\Overtrue\LaravelVersionable\Version> $versions
 */
trait Versionable
{
    protected static bool $versioning = true;

    protected bool $forceDeleteVersion = false;

    // You can add these properties to you versionable model
    //protected $versionable = [];
    //protected $dontVersionable = ['*'];

    // You can define this variable in class, that used this trait to change Model(table) for versions
    // Model MUST extend \Overtrue\LaravelVersionable\Version
    //public string $versionModel;

    public static function bootVersionable(): void
    {
        static::created(function (Model $model) {
            if (static::$versioning) {
                // init version should include all fields, not only $versionable?
                /** @var \Overtrue\LaravelVersionable\Versionable|Model $model */
                $model->createInitialVersion($model);
            }
        });

        static::updating(function (Model $model) {
            // ensure the initial version exists when updating
            /** @var \Overtrue\LaravelVersionable\Versionable $model */
            if (static::$versioning && $model->versions()->count() === 0) {
                $model->createInitialVersion($model);
            }
        });

        static::updated(function (Model $model) {
            if (static::$versioning) {
                /** @var \Overtrue\LaravelVersionable\Versionable $model */
                $model->createVersion();
            }
        });

        static::deleted(
            function (Model $model) {
                /* @var \Overtrue\LaravelVersionable\Versionable|\Overtrue\LaravelVersionable\Version$model */
                if ($model->isForceDeleting()) {
                    $model->forceRemoveAllVersions();
                }
            }
        );
    }

    /**
     * @param  string|\DateTimeInterface|null  $time
     * @return ?Version
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function createVersion(array $replacements = [], $time = null): ?Version
    {
        if ($this->shouldBeVersioning() || ! empty($replacements)) {
            return tap(Version::createForModel($this, $replacements, $time), function () {
                $this->removeOldVersions($this->getKeepVersionsCount());
            });
        }

        return null;
    }

    public function createInitialVersion(Model $model): Version
    {
        $refreshedModel = static::query()->findOrFail($model->getKey());

        return tap(Version::createForModel($refreshedModel, $refreshedModel->getAttributes(), $refreshedModel->updated_at), function (Version $version) {
            $version->forceFill(['is_initial' => true])->saveQuietly();
        });
    }

    public function versions(): MorphMany
    {
        return $this->morphMany($this->getVersionModel(), 'versionable');
    }

    public function versionHistory()
    {
        return $this->versions()->orderLatestFirst();
    }

    public function lastVersion(): MorphOne
    {
        return $this->latestVersion();
    }

    public function latestVersion(): MorphOne
    {
        return $this->morphOne($this->getVersionModel(), 'versionable')->orderLatestFirst();
    }

    public function firstVersion(): MorphOne
    {
        return $this->morphOne($this->getVersionModel(), 'versionable')->orderOldestFirst();
    }

    /**
     * Get the version for a specific time.
     *
     * @param  string|\DateTimeInterface|null  $time
     * @param  \DateTimeZone|string|null  $tz
     * @return ?\Overtrue\LaravelVersionable\Version
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function versionAt($time = null, $tz = null): ?Version
    {
        return $this->versionHistory()
            ->where('created_at', '<=', Carbon::parse($time, $tz))
            ->first();
    }

    public function getVersion(int $id): ?Version
    {
        return $this->versions()->find($id);
    }

    public function getThrashedVersions()
    {
        return $this->versions()->onlyTrashed()->get();
    }

    public function restoreTrashedVersion(int $id)
    {
        return $this->versions()->onlyTrashed()->whereId($id)->restore();
    }

    public function revertToVersion(int $id): bool
    {
        return $this->versions()->findOrFail($id)->revert();
    }

    public function removeOldVersions(int $keep = 1): void
    {
        if ($keep <= 0) {
            return;
        }

        $this->versionHistory()->where('is_initial', false)->skip($keep)->take(PHP_INT_MAX)->get()->each->delete();
    }

    public function removeVersions(array $ids)
    {
        if ($this->forceDeleteVersion) {
            return $this->forceRemoveVersions($ids);
        }

        return $this->versions()->where('is_initial', false)->find($ids)->each->delete();
    }

    public function removeVersion(int $id)
    {
        if ($this->forceDeleteVersion) {
            return $this->forceRemoveVersion($id);
        }

        return $this->versions()->findOrFail($id)->delete();
    }

    public function removeAllVersions(): void
    {
        if ($this->forceDeleteVersion) {
            $this->forceRemoveAllVersions();
        }

        $this->versions->where('is_initial', false)->each->delete();
    }

    public function forceRemoveVersion(int $id)
    {
        return $this->versions()->findOrFail($id)->forceDelete();
    }

    public function forceRemoveVersions(array $ids)
    {
        return $this->versions()->findMany($ids)->each->forceDelete();
    }

    public function forceRemoveAllVersions(): void
    {
        $this->versions->each->forceDelete();
    }

    public function shouldBeVersioning(): bool
    {
        // xxx: fix break change
        if (method_exists($this, 'shouldVersioning')) {
            return call_user_func([$this, 'shouldVersioning']);
        }

        return ! empty($this->getVersionableAttributes());
    }

    public function getVersionableAttributes(array $replacements = []): array
    {
        return match ($this->getVersionStrategy()) {
            VersionStrategy::DIFF => $this->getDiffAttributes($replacements),
            VersionStrategy::SNAPSHOT => $this->getSnapshotAttributes($replacements),
        };
    }

    protected function getDiffAttributes(array $replacements = []): array
    {
        return array_merge($this->getDirty(), $replacements);
    }

    protected function getSnapshotAttributes(array $replacements = []): array
    {
        $versionable = $this->getVersionable();
        $dontVersionable = $this->getDontVersionable();

        $attributes = count($versionable) > 0 ? $this->only($versionable) : $this->getAttributes();

        return Arr::except(array_merge($attributes, $replacements), $dontVersionable);
    }

    /**
     * @throws \Exception
     */
    public function setVersionable(array $attributes): static
    {
        if (! \property_exists($this, 'versionable')) {
            throw new \Exception('Property $versionable not exist.');
        }

        $this->versionable = $attributes;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function setDontVersionable(array $attributes): static
    {
        if (! \property_exists($this, 'dontVersionable')) {
            throw new \Exception('Property $dontVersionable not exist.');
        }

        $this->dontVersionable = $attributes;

        return $this;
    }

    public function getVersionable(): array
    {
        return \property_exists($this, 'versionable') ? $this->versionable : [];
    }

    public function getDontVersionable(): array
    {
        return \property_exists($this, 'dontVersionable') ? $this->dontVersionable : [];
    }

    public function getVersionStrategy(): VersionStrategy
    {
        return \property_exists($this, 'versionStrategy') ? $this->versionStrategy : VersionStrategy::DIFF;
    }

    /**
     * @throws \Exception
     */
    public function setVersionStrategy(VersionStrategy $strategy): static
    {
        if (! \property_exists($this, 'versionStrategy')) {
            throw new \Exception('Property $versionStrategy not exist.');
        }

        $this->versionStrategy = $strategy;

        return $this;
    }

    public function getVersionModel(): string
    {
        return $this->versionModel ?? config('versionable.version_model');
    }

    public function getVersionUserId()
    {
        return auth()->id() ?? $this->getAttribute(\config('versionable.user_foreign_key'));
    }

    public function getKeepVersionsCount(): string
    {
        return config('versionable.keep_versions', 0);
    }

    public function versionableFromArray(array $attributes): array
    {
        if (count($this->getVersionable()) > 0) {
            return \array_intersect_key($attributes, array_flip($this->getVersionable()));
        }

        if (count($this->getDontVersionable()) > 0) {
            return \array_diff_key($attributes, array_flip($this->getDontVersionable()));
        }

        return $attributes;
    }

    public static function getVersioning(): bool
    {
        return static::$versioning;
    }

    public static function withoutVersion(callable $callback): void
    {
        $lastState = static::$versioning;

        static::disableVersioning();

        \call_user_func($callback);

        static::$versioning = $lastState;
    }

    public static function disableVersioning(): void
    {
        static::$versioning = false;
    }

    public static function enableVersioning(): void
    {
        static::$versioning = true;
    }
}
