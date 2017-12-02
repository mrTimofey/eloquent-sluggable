Sluggable Eloquent model class.

## Requirements

* PHP 7.1
* Eloquent models

## Features

* `findBySlug`, `findBySlugOrFail`, `findByAny`, `findByAnyOrFail`
* Automatic unique slug generation on create (if not set manually)
* Both slug or ID route resolving (ID field should be integer)

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
	 * @var string attribute name that can be a source to generate slug
	 */
	protected static $slugSource = 'name';

	/**
	 * @var string attribute name containing a slug
	 */
	protected static $slugField = 'slug';

	/**
	 * @var bool slug can be null
	 */
	protected static $slugNullable = false;
}
```