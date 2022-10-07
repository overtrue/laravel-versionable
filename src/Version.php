<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Model|\Overtrue\LaravelVersionable\Versionable $versionable
 * @property array $contents
 * @property int $id
 */
class Version extends Model
{
    use SoftDeletes;

    protected $guarded = [];

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
     * @param  array  $attributes
     * @param  string|DateTimeInterface|null  $time
     * @return \Overtrue\LaravelVersionable\Version
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public static function createForModel(Model $model, array $attributes = [], $time = null): Version
    {
        /* @var \Overtrue\LaravelVersionable\Versionable|Model $model */
        $versionClass = $model->getVersionModel();
        $versionConnection = $model->getConnectionName();

        $version = new $versionClass();
        $version->setConnection($versionConnection);

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{\config('versionable.user_foreign_key')} = $model->getVersionUserId();
        $version->contents = $model->getVersionableAttributes($attributes);

        if ($time) { 
            $version->created_at = Carbon::parse($time);
        }

        $version->save();

        return $version;
    }

    public function revert(): bool
    {
        return $this->revertWithoutSaving()->save();
    }

    public function revertWithoutSaving(): ?Model
    {
        return $this->versionable->forceFill($this->contents);
    }

    public function scopeOrderOldestFirst(Builder $query): Builder
    {
        return $query->oldest()->oldest('id');
    }

    public function scopeOrderLatestFirst(Builder $query): Builder
    {
        return $query->latest()->latest('id');
    }

    public function previousVersion(): ?static
    {
        return $this->versionable->history()
            ->where(function ($query) {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function ($query) {
                        $query->where('id', '<', $this->getKey())
                            ->where('created_at', '<=', $this->created_at);
                    });
            })
            ->first();
    }

    public function nextVersion(): ?static
    {
        return $this->versionable->versions()
            ->where(function ($query) {
                $query->where('created_at', '>', $this->created_at)
                    ->orWhere(function ($query) {
                        $query->where('id', '>', $this->getKey())
                            ->where('created_at', '>=', $this->created_at);
                    });
            })
            ->orderOldestFirst()
            ->first();
    }

    public function diff(Version $toVersion = null, array $differOptions = [], array $renderOptions = []): Diff
    {
        if (!$toVersion) {
            $toVersion = $this->previousVersion() ?? new static();
        }

        return new Diff($this, $toVersion, $differOptions, $renderOptions);
    }
}
