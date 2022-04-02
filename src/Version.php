<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use SebastianBergmann\Diff\Differ;

/**
 * @property Model $versionable
 * @property array $contents
 */
class Version extends Model
{
    use SoftDeletes;

    /**
     * @var array
     */
    protected $casts = [
        'contents' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|null
     */
    public function user()
    {
        $useSoftDeletes = \in_array(SoftDeletes::class, \class_uses(\config('versionable.user_model')));

        return \tap(
            $this->belongsTo(
                \config('versionable.user_model'),
                \config('versionable.user_foreign_key')
            ),
            fn ($relation) => $useSoftDeletes ? $relation->withTrashed() : $relation
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function versionable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('versionable');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array                                $attributes
     *
     * @return \Overtrue\LaravelVersionable\Version
     */
    public static function createForModel(Model $model, array $attributes = []): Version
    {
        /* @var \Overtrue\LaravelVersionable\Versionable|Model $model */
        $versionClass = $model->getVersionModel();

        $version = new $versionClass();

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{\config('versionable.user_foreign_key')} = $model->getVersionUserId();
        $version->contents = \array_merge($attributes, $model->getVersionableAttributes());

        $version->save();

        return $version;
    }

    /**
     * @return bool
     */
    public function revert()
    {
        return $this->versionable->forceFill($this->contents)->save();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|null  $model
     */
    public function revertWithoutSaving(): ?Model
    {
        return $this->versionable->forceFill($this->contents);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     *
     * @return string
     */
    public function diff(Model $model = null): string
    {
        /* @var \Overtrue\LaravelVersionable\Versionable|Model $model */
        $model || $model = $this->versionable;

        if ($model instanceof Version) {
            $source = $model->contents;
        } else {
            if (!\in_array(Versionable::class, \class_uses($model))) {
                throw new \InvalidArgumentException(\sprintf('Model %s is not versionable.', \get_class($model)));
            }

            $source = $model->versionableFromArray($this->versionable->toArray());
        }

        return (new Differ())->diff(Arr::only($source, \array_keys($this->contents)), $this->contents);
    }
}
