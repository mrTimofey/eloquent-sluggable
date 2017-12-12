<?php

namespace MrTimofey\EloquentSluggable;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;

trait Sluggable
{
    /**
     * @var string attribute name that can be a source to generate slug
     */
    // protected static $slugSource = 'name';

    /**
     * @var string attribute name containing a slug
     */
    // protected static $slugField = 'slug';

    /**
     * @var bool slug can be null
     */
    // protected static $slugNullable = false;

    /**
     * @var bool is already slugified (prevent double slug generation)
     */
    protected $slugified = false;

    protected static function getSlugSourceName(): string
    {
        return static::$slugSource ?? 'name';
    }

    protected static function getSlugName(): string
    {
        return static::$slugField ?? 'slug';
    }

    protected static function slugNullable(): bool
    {
        return !empty(static::$slugNullable);
    }

    protected static function strToSlug(string $source): string
    {
        return str_slug($source);
    }

    protected function isSlugUnique(string $slug): bool
    {
        $q = $this->newQueryWithoutScopes()->where(static::getSlugName(), $slug);

        // exclude item itself from checking
        if ($this->exists) {
            $q->where($this->getKeyName(), '!=', $this->getKey());
        }
        return !$q->exists();
    }

    public function getSlugSource(): ?string
    {
        $v = $this->attributes[static::getSlugSourceName()] ?? null;
        if ($v === null) {
            return null;
        }
        return (string)$v;
    }

    public function getSlug(): ?string
    {
        $v = $this->attributes[static::getSlugName()] ?? null;
        if ($v === null) {
            return null;
        }
        return (string)$v;
    }

    public function setSlug(string $value): void
    {
        $this->attributes[static::getSlugName()] = $value;
        $this->slugified = true;
    }

    /**
     * Generate unique slug value.
     * @return string|null
     */
    public function generateSlug(): ?string
    {
        $slug = $this->getSlug() ?: $this->getSlugSource();

        if ($slug === null) {
            if (static::slugNullable()) {
                return null;
            }
            return $this->getKey() ?: str_random();
        }

        // process slug value or generate it from source
        $slug = static::strToSlug($slug);

        // check unique
        $original = $slug;
        $num = 0;

        while (!$this->isSlugUnique($slug)) {
            // append next number to slug until slug becomes unique
            $slug = $original . '-' . (++$num);
        }

        return $slug;
    }

    /**
     * Find by slug.
     * @param string $slug
     * @return self|Model|null
     */
    public static function findBySlug($slug): ?self
    {
        return static::query()->where(static::getSlugName(), $slug)->limit(1)->first();
    }

    /**
     * Find by slug or throw ModelNotFoundException if no result.
     * @param string $slug
     * @throws ModelNotFoundException
     * @return self
     */
    public static function findBySlugOrFail($slug): self
    {
        $item = static::findBySlug($slug);
        if (!$item) {
            throw new ModelNotFoundException('No results for slug ' . $slug);
        }
        return $item;
    }

    /**
     * Find by id or slug.
     * @param string|int $slug slug or id
     * @return self|Model|null
     */
    public static function findByAny($slug): ?self
    {
        if (\is_int($slug)) {
            return static::query()->find($slug);
        }
        $id = (int)$slug;
        if ($id && \strlen((string)$id) === \strlen($slug)) {
            return static::query()->find($id);
        }
        return static::findBySlug($slug);
    }

    /**
     * Find by id or slug or throw ModelNotFoundException if no result.
     * @param string|int $slug
     * @throws ModelNotFoundException
     * @return self
     */
    public static function findByAnyOrFail($slug): self
    {
        $item = static::findByAny($slug);
        if (!$item) {
            throw new ModelNotFoundException('No results for key or slug');
        }
        return $item;
    }

    /**
     * Override default to use slug field as a default field for routing.
     * @return string field name
     */
    public function getRouteKeyName(): string
    {
        return static::getSlugName();
    }

    /**
     * Try to resolve route binding by finding ID or slug.
     * @param $value
     * @return self|Model|null
     */
    public function resolveRouteBinding($value): ?self
    {
        return static::findByAny($value);
    }

    /**
     * Unique validation rule for slug field which ignores item itself if it exists.
     * @return string
     */
    public function slugUniqueValidationRule(): string
    {
        return 'unique:' . $this->getTable() . ',' . static::getSlugName() .
            ($this->exists ? (',' . $this->getKey() . ',' . $this->getKeyName()) : '');
    }

    /**
     * Boot hook for this trait.
     * @see Model::bootTraits
     */
    public static function bootSluggable(): void
    {
        // generate slug automatically after saving
        static::saving(function (self $item) {
            if (!$item->slugified && !$item->getSlug()) {
                $item->setSlug($item->generateSlug());
            }
        });
    }
}