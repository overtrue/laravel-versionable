<?php

namespace Overtrue\LaravelVersionable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

use function class_uses;
use function config;
use function in_array;
use function tap;

/**
 * @property Model|\Overtrue\LaravelVersionable\Versionable $versionable
 * @property array $contents
 * @property int $id
 * @property bool $is_initial
 * @property Carbon $created_at
 * @property Carbon $updated_at
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

    protected static function booted()
    {
        static::deleting(function (Version $version) {
            if ($version->is_initial && ! $version->isForceDeleting()) {
                throw new \Exception('You cannot delete the init version');
            }
        });
    }

    public function user(): ?BelongsTo
    {
        $useSoftDeletes = in_array(SoftDeletes::class, class_uses(config('versionable.user_model')));

        return tap(
            $this->belongsTo(
                config('versionable.user_model'),
                config('versionable.user_foreign_key')
            ),
            fn ($relation) => $useSoftDeletes ? $relation->withTrashed() : $relation
        );
    }

    public function versionable(): MorphTo
    {
        return $this->morphTo('versionable');
    }

    /**
     * @param  string|\DateTimeInterface|null  $time
     *
     * @throws \Carbon\Exceptions\InvalidFormatException
     */
    public static function createForModel(Model $model, array $replacements = [], $time = null): Version
    {
        /* @var \Overtrue\LaravelVersionable\Versionable|Model $model */
        $versionClass = $model->getVersionModel();
        $versionConnection = $model->getConnectionName();

        $version = new $versionClass();
        $version->setConnection($versionConnection);

        $version->versionable_id = $model->getKey();
        $version->versionable_type = $model->getMorphClass();
        $version->{config('versionable.user_foreign_key')} = $model->getVersionUserId();
        $version->contents = $model->getVersionableAttributes($replacements);

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
        switch ($this->versionable->getVersionStrategy()) {
            case VersionStrategy::DIFF:
                // v1 + ... + vN
                $versionsBeforeThis = $this->previousVersions()->orderOldestFirst()->get();
                foreach ($versionsBeforeThis as $version) {
                    $this->forceFill($version->contents);
                }
                break;
            case VersionStrategy::SNAPSHOT:
                // v1 + vN
                /** @var \Overtrue\LaravelVersionable\Version $initVersion */
                $initVersion = $this->versionable->versions()->first();
                $this->forceFill($initVersion->contents);
        }

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

    public function previousVersions(): ?static
    {
        return $this->versionable->versionHistory()
            ->where(function ($query) {
                $query->where('created_at', '<', $this->created_at)
                    ->orWhere(function ($query) {
                        $query->where('id', '<', $this->getKey())
                            ->where('created_at', '<=', $this->created_at);
                    });
            });
    }

    public function previousVersion(): ?static
    {
        return $this->previousVersions()->orderLatestFirst()->first();
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

    public function diff(?Version $toVersion = null, array $differOptions = [], array $renderOptions = []): Diff
    {
        if (! $toVersion) {
            $toVersion = $this->previousVersion() ?? new static();
        }

        return new Diff($this, $toVersion, $differOptions, $renderOptions);
    }
}
