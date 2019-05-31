<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use SebastianBergmann\Diff\Differ;

/**
 * Class Version
 *
 * @property Model $versionable
 * @property array $contents
 */
class Version extends Model
{
    protected $casts = [
        'contents' => 'array',
    ];

    public function versionable()
    {
        return $this->morphTo('versionable');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return \Overtrue\LaravelVersionable\Version
     */
    public static function createForModel(Model $model)
    {
        $versionClass = $model->getVersionModel();

        $version = new $versionClass();

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{\config('versionable.user_foreign_key')} = \auth()->id();
        $version->contents = $model->getVersionableAttributes();

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
     * @param \Illuminate\Database\Eloquent\Model|null $model
     *
     * @return string
     */
    public function diff(Model $model = null)
    {
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
