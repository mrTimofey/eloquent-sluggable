Slugs for Eloquent models.

## Features

* `findBySlug`, `findBySlugOrFail`, `findByAny`, `findByAnyOrFail`
* Automatic slug generation on create (if not set manually) by transliterating source attribute
* Automatic unique constraint check after any slug modification on each saving
	(slug will be appended with -1/2/3/... until becomes unique)
* Both slug or ID route resolving (assuming ID field is integer)
* Almost anything can be customized by method overriding or class fields

## Requirements

* PHP 7.1
* Eloquent models

## Install

```bash
composer require mr-timofey/eloquent-sluggable
```

## Usage

Just use `MrTimofey\EloquentSluggable\Sluggable` trait by any of your model classes. Define fields if needed:

```php
class MyModel extends \Illuminate\Database\Eloquent\Model {
	use \MrTimofey\EloquentSluggable\Sluggable;

	/**
	 * @var string attribute name that used as a source for transliteration
	 */
	protected static $slugSource = 'name';

	/**
	 * @var string attribute name containing a slug itself
	 */
	protected static $slugField = 'slug';

	/**
	 * @var bool slug can be null
	 */
	protected static $slugNullable = false;
}
```

See trait source code if you need more customizations.