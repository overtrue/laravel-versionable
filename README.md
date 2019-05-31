<h1 align="center"> laravel-versionable </h1>

<p align="center"> Make Laravel model versionable.</p>


## Installing

```shell
$ composer require overtrue/laravel-versionable -vvv
```

Optional, you can publish the config file:

```bash
$ php artisan vendor:publish --provider="Overtrue\\LaravelVersionable\\ServiceProvider" --tag=config
```

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

Get last version

```php
$post->lastVersion;
```

### Reversion

Reversion a model instance to the specified version:

```php
$post->getVersion(3)->revert();

// or

$post->revertToVersion(3);
```

### Remove All versions

```php
$post->removeAllVersions();
```

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT