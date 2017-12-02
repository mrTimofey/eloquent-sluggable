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

    protected static function slugSource(): string
    {
        return static::$slugSource ?? 'name';
    }

    protected static function slugField(): string
    {
        return static::$slugField ?? 'slug';
    }

    protected static function slugNullable(): bool
    {
        return !empty(static::$slugNullable);
    }

    /**
     * Generate unique slug value.
     * @return string|null
     */
    public function generateSlug(): ?string
    {
        $slug = $this->attributes[static::slugField()] ?? null;
        $slug = $slug ?: ($this->attributes[static::slugSource()] ?? null);

        if ($slug === null) {
            if (static::slugNullable()) {
                return null;
            }
            return $this->getKey() ?: str_random();
        }

        // process slug value or generate it from source
        $slug = str_slug($slug);

        // check unique
        $original = $slug;
        $num = 0;
        while (true) {
            $q = $this->newQueryWithoutScopes()->where(static::slugField(), $slug);

            // exclude item itself from checking
            if ($this->exists) {
                $q->where($this->getKeyName(), '!=', $this->getKey());
            }
            if (!$q->exists()) {
                break;
            }

            // append next number to slug (until slug becomes unique)
            $slug = $original . '-' . (++$num);
        }

        return $slug;
    }

    /**
     * Generate slug value and update slug attribute.
     * @return self $this
     */
    public function slugify(): self
    {
        $this->attributes[static::slugField()] = $this->generateSlug();
        $this->slugified = true;
        return $this;
    }

    /**
     * Find by slug.
     * @param string $slug
     * @return self|Model|null
     */
    public static function findBySlug($slug): ?self
    {
        return static::query()->where(static::slugField(), $slug)->limit(1)->first();
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
        return static::slugField();
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
        return 'unique:' . $this->getTable() . ',' . static::slugField() .
            ($this->exists ? (',' . $this->getKey() . ',' . $this->getKeyName()) : '');
    }

    public static function boot(): void
    {
        parent::boot();

        // generate slug automatically after saving
        static::saving(function (self $item) {
            // skip existing items with already set slug
            if ($item->slugified || ($item->exists && !$item->isDirty(static::slugField()))) {
                return;
            }
            $item->slugify();
        });
    }
}