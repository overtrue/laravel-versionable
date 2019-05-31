<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait Versionable
{
    // You can add these properties to you versionable model
    //protected $versionable = [];
    //protected $dontVersionable = ['*'];

    public static function bootVersionable()
    {
        static::saved(function (Model $model) {
            if ($model->shouldVersioning()) {
                Version::createForModel($model);
                $model->removeOldVersions($model->getKeepVersionsCount());
            }
        });

        static::deleted(function (Model $model) {
            $model->removeAllVersions();
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function versions(): MorphMany
    {
        return $this->morphMany(\config('versionable.version_model'), 'versionable')->latest('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function lastVersion(): MorphOne
    {
        return $this->morphOne(\config('versionable.version_model'), 'versionable')->latest('id');
    }

    /**
     * @param int $id
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getVersion($id)
    {
        return $this->versions()->find($id);
    }

    /**
     * @param int $id
     *
     * @return mixed
     */
    public function revertToVersion($id)
    {
        return $this->versions()->findOrFail($id)->revert();
    }

    /**
     * @param int $keep
     */
    public function removeOldVersions(int $keep): void
    {
        if ($keep <= 0) {
            return;
        }

        $this->versions()->skip($keep)->take($keep)->get()->each->delete();
    }

    public function removeAllVersions()
    {
        $this->versions->each->delete();
    }

    /**
     * @return bool
     */
    public function shouldVersioning(): bool
    {
        return !empty($this->getVersionableAttributes());
    }

    /**
     * @return array
     */
    public function getVersionableAttributes(): array
    {
        $changes = $this->getDirty();

        if (empty($changes)) {
            return [];
        }

        return $this->versionableFromArray($changes);
    }

    /**
     * @param array $attributes
     *
     * @return $this
     * @throws \Exception
     */
    public function setVersionable(array $attributes)
    {
        if (!\property_exists($this, 'versionable')) {
            throw new \Exception('Property $versionable not exist.');
        }

        $this->versionable = $attributes;

        return $this;
    }

    /**
     * @param array $attributes
     *
     * @return $this
     * @throws \Exception
     */
    public function setDontVersionable(array $attributes)
    {
        if (!\property_exists($this, 'dontVersionable')) {
            throw new \Exception('Property $dontVersionable not exist.');
        }

        $this->dontVersionable = $attributes;

        return $this;
    }

    /**
     * @return array
     */
    public function getVersionable(): array
    {
        return \property_exists($this, 'versionable') ? $this->versionable : [];
    }

    /**
     * @return array
     */
    public function getDontVersionable(): array
    {
        return \property_exists($this, 'dontVersionable') ? $this->dontVersionable : [];
    }

    /**
     * @return string
     */
    public function getVersionModel(): string
    {
        return config('versionable.version_model');
    }

    /**
     * @return string
     */
    public function getKeepVersionsCount(): string
    {
        return config('versionable.keep_versions', 0);
    }

    /**
     * Get the versionable attributes of a given array.
     *
     * @param array $attributes
     *
     * @return array
     */
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
}
