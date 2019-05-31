<h1 align="center"> laravel-versionable </h1>

<p align="center"> Make Laravel model versionable.</p>


## Installing

```shell
$ composer require overtrue/laravel-versionable -vvv
```

## Usage

Add `Overtrue\LaravelVersionable\Versionable` trait to the model and set versionable attributes:

```php
use Overtrue\LaravelVersionable\Versionable;

class Post extends Model
{
    use Versionable;
    
    protected $versionable = ['title', 'content'];
    
    <...>
}
```

//todo


## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/overtrue/laravel-versionable/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## License

MIT