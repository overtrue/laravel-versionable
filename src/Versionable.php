<?php

/*
 * This file is part of the overtrue/laravel-versionable.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait Versionable
{
    static protected $versioning = true;

    // You can add these properties to you versionable model
    //protected $versionable = [];
    //protected $dontVersionable = ['*'];

    public static function bootVersionable()
    {
        static::saved(function (Model $model) {
            self::createVersionForModel($model);
        });

        static::deleted(function (Model $model) {
            if ($model->forceDeleting) {
                $model->removeAllVersions();
            } else {
                self::createVersionForModel($model);
            }
        });
    }

    private static function createVersionForModel(Model $model): void
    {
        if (self::$versioning && $model->shouldVersioning()) {
            Version::createForModel($model);
            $model->removeOldVersions($model->getKeepVersionsCount());
        }
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

        $this->versions()->skip($keep)->take(PHP_INT_MAX)->get()->each->delete();
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

        switch ($this->getVersionStrategy()) {
            case VersionStrategy::DIFF:
                $contents = $changes;
                break;
            case VersionStrategy::SNAPSHOT:
                $contents = $this->attributesToArray();
                break;
            default:
                $contents = $changes;
        }

        return $this->versionableFromArray($contents);
    }

    /**
     * @param array $attributes
     *
     * @return $this
     *
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
     *
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
    public function getVersionStrategy()
    {
        return \property_exists($this, 'versionStrategy') ? $this->versionStrategy : VersionStrategy::DIFF;
    }

    /**
     * @param string $strategy
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setVersionStrategy(string $strategy)
    {
        if (!\property_exists($this, 'versionStrategy')) {
            throw new \Exception('Property $versionStrategy not exist.');
        }

        $this->versionStrategy = $strategy;

        return $this;
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

    /**
     * @param callable $callback
     */
    public static function withoutVersion(callable $callback)
    {
        self::$versioning = false;

        \call_user_func($callback);

        self::$versioning = true;
    }
    /**
     * transform the contents by versionTrans
     *
     * @return array
     */
    public function versionsTrans()
    {
        $data = $this->versions;
        $data->transform(function ($item, $key) {
            $item['contents'] = $this->transformContent($item['contents']);
            return $item;
        });
        return $data;
    }
    /**
     *
     * @param array $data
     *
     * @return array
     */
    public function transformContent($data)
    {
        $trans = $this->versionTrans;
        if ($trans) {
            $res = [];
            foreach ($data as $key => $value) {
                if (isset($trans[$key])) {
                    $res[$trans[$key]] = $value;
                } else {
                    $res[$key] = $value;
                }
            }
            return $res;
        }
        return $data;
    }
}
