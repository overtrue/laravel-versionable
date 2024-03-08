<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

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

    public static function bootVersionable()
    {
        static::updating(
            function (Model $model) {
                if (static::$versioning && $model->versions()->count() === 0) {
                    $existingModel = self::find($model->id);

                    Version::createForModel($existingModel, $existingModel->only($existingModel->getVersionable()));
                }
            }
        );

        static::saved(
            function (Model $model) {
                $model->autoCreateVersion();
            }
        );

        static::deleted(
            function (Model $model) {
                /* @var \Overtrue\LaravelVersionable\Versionable|Model $model */
                if ($model->forceDeleting) {
                    $model->forceRemoveAllVersions();
                } else {
                    $model->autoCreateVersion();
                }
            }
        );
    }

    private function autoCreateVersion(): ?Version
    {
        if (static::$versioning) {
            return $this->createVersion();
        }

        return null;
    }

    /**
     * @param  string|DateTimeInterface|null  $time
     * @return ?Version
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function createVersion(array $attributes = [], $time = null): ?Version
    {
        if ($this->shouldBeVersioning() || ! empty($attributes)) {
            return tap(Version::createForModel($this, $attributes, $time), function () {
                $this->removeOldVersions($this->getKeepVersionsCount());
            });
        }

        return null;
    }

    public function versions(): MorphMany
    {
        return $this->morphMany($this->getVersionModel(), 'versionable');
    }

    public function history(): MorphMany
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
     * @param  string|DateTimeInterface|null  $time
     * @param  DateTimeZone|string|null  $tz
     * @return static
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public function versionAt($time = null, $tz = null): ?Version
    {
        return $this->history()
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

        $this->history()->skip($keep)->take(PHP_INT_MAX)->get()->each->delete();
    }

    public function removeVersions(array $ids)
    {
        if ($this->forceDeleteVersion) {
            return $this->forceRemoveVersions($ids);
        }

        return $this->versions()->find($ids)->each->delete();
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

        $this->versions->each->delete();
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

    public function getVersionableAttributes(array $attributes = []): array
    {
        $changes = $this->getDirty();

        if (empty($changes) && empty($attributes)) {
            return [];
        }

        $changes = $this->versionableFromArray($changes);
        $changedKeys = array_keys($changes);

        if ($this->getVersionStrategy() === VersionStrategy::SNAPSHOT && (! empty($changes) || ! empty($attributes))) {
            $changedKeys = array_keys($this->getAttributes());
        }

        // to keep casts and mutators works, we need to get the updated attributes from the model
        return \array_merge(array_intersect_key($this->getAttributes(), array_flip($changedKeys)), $attributes);
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

    public function getVersionStrategy(): string
    {
        return \property_exists($this, 'versionStrategy') ? $this->versionStrategy : VersionStrategy::DIFF;
    }

    /**
     * @throws \Exception
     */
    public function setVersionStrategy(string $strategy): static
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

    public static function getVersioning()
    {
        return static::$versioning;
    }

    public static function withoutVersion(callable $callback)
    {
        $lastState = static::$versioning;

        static::disableVersioning();

        \call_user_func($callback);

        static::$versioning = $lastState;
    }

    public static function disableVersioning()
    {
        static::$versioning = false;
    }

    public static function enableVersioning()
    {
        static::$versioning = true;
    }
}
