<h1 align="center"> laravel-versionable </h1>

<p align="center"> ⏱️ Make Laravel model versionable.</p>

<p align="center">
<a href="https://github.com/overtrue/laravel-versionable/actions"><img src="https://github.com/overtrue/laravel-versionable/workflows/CI/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/overtrue/laravel-versionable"><img src="https://poser.pugx.org/overtrue/laravel-versionable/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/overtrue/laravel-versionable"><img src="https://poser.pugx.org/overtrue/laravel-versionable/v/unstable.svg" alt="Latest Unstable Version"></a>
<a href="https://scrutinizer-ci.com/g/overtrue/laravel-versionable/?branch=master"><img src="https://scrutinizer-ci.com/g/overtrue/laravel-versionable/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality"></a>
<a href="https://scrutinizer-ci.com/g/overtrue/laravel-versionable/?branch=master"><img src="https://scrutinizer-ci.com/g/overtrue/laravel-versionable/badges/coverage.png?b=master" alt="Code Coverage"></a>
<a href="https://packagist.org/packages/overtrue/laravel-versionable"><img src="https://poser.pugx.org/overtrue/laravel-versionable/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/overtrue/laravel-versionable"><img src="https://poser.pugx.org/overtrue/laravel-versionable/license" alt="License"></a>
</p>


It's a minimalist way to make your model support version history, and it's very simple to roll back to the specified version.


## Requirement

 1. PHP >= 7.4
 2. laravel/framework >= 5.8|6.0|7.0

## Features
- Keep the specified number of versions.
- Whitelist and blacklist for versionable attributes.
- Easily roll back to the specified version.
- Record only changed attributes.
- Easy to customize.


## Installing

```shell
$ composer require overtrue/laravel-versionable -vvv
```

Optional, you can publish the config file:

```bash
$ php artisan vendor:publish --provider="Overtrue\LaravelVersionable\ServiceProvider" --tag=config
```

And if you want to custom the migration of the versions table, you can publish the migration file to your database path:

```bash
$ php artisan vendor:publish --provider="Overtrue\LaravelVersionable\ServiceProvider" --tag=migrations
```

> After you published the migration files, please update `'migrations' => false` in the config file `config/versionable.php` to disable load the package migrations. 

Then run this command to create a database migration:

```bash
$ php artisan migrate
```

## Usage

Add `Overtrue\LaravelVersionable\Versionable` trait to the model and set versionable attributes:

```php
use Overtrue\LaravelVersionable\Versionable;

class Post extends Model
{
    use Versionable;
    
    /**
     * Versionable attributes
     *
     * @var array
     */
    protected $versionable = ['title', 'content'];
    
    <...>
}
```

Versions will be created on vensionable model saved.

```php
$post = Post::create(['title' => 'version1', 'content' => 'version1 content']);
$post->update(['title' => 'version2']);
```

### Get versions

Get all versions

```php
$post->versions;
```

Get latest version

```php
$post->latestVersion;
// or
$post->lastVersion;
```

### Reversion

Reversion a model instance to the specified version:

```php
$post->getVersion(3)->revert();

// or

$post->revertToVersion(3);
```
#### Reversion without saving

```php
$newPost = $post->revertWithoutSaving()
```

### Remove versions

```php
// soft delete
$post->removeVersion($versionId = 1);
$post->removeVersions($versionIds = [1, 2, 3]);
$post->removeAllVersions();

// force delete
$post->forceRemoveVersion($versionId = 1);
$post->forceRemoveVersions($versionIds = [1, 2, 3]);
$post->forceRemoveAllVersions();
```

### Restore deleted version by id

```php
$post->restoreTrashedVersion($id);
```


### Temporarily disable versioning

```php
// create
Post::withoutVersion(function () use (&$post) {
    Post::create(['title' => 'version1', 'content' => 'version1 content']);
});

// update
Post::withoutVersion(function () use ($post) {
    $post->update(['title' => 'updated']);
});
```

### Custom Version Store strategy

You can set the following different version policies through property `protected $versionStrategy`:

-  `Overtrue\LaravelVersionable::DIFF` - Version content will only contain changed attributes (Default Strategy).
-  `Overtrue\LaravelVersionable::SNAPSHOT` - Version content will contain all versionable attributes values. 

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT
