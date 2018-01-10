<?php

namespace MrTimofey\EloquentSluggable;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $slugSource static attribute name that can be a source to generate slug
 * @property-read string $slugField static attribute name containing a slug
 * @property-read string $slugNullable static slug can be null
 */
trait Sluggable
{
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

    /**
     * If slug is nullable and both slug and slug source fields are null
     * than slug is not generated and remains null.
     * @return bool
     */
    protected static function slugNullable(): bool
    {
        return !empty(static::$slugNullable);
    }

    protected function strToSlug(string $source): string
    {
        return str_slug($source);
    }

    /**
     * Check argument to be a unique slug.
     * @param string $slug
     * @return bool
     */
    protected function isSlugUnique(string $slug): bool
    {
        $q = $this->newQueryWithoutScopes()->where(static::getSlugName(), $slug);

        // exclude item itself from checking
        if ($this->exists) {
            $q->where($this->getKeyName(), '!=', $this->getKey());
        }
        return !$q->exists();
    }

    /**
     * Suggest a new different slug variation after unique check was failed.
     * @param string $original original non-unique slug
     * @param int $iterationNum current unique checking iteration (1+)
     * @param string $previousSlug previous suggested slug variant
     * @return string
     */
    protected function suggestUniqueSlug(string $original, int $iterationNum, string $previousSlug): string
    {
        return $original . '-' . $iterationNum;
    }

    public function getSlugSource(): ?string
    {
        $v = $this->attributes[static::getSlugSourceName()] ?? null;
        return $v === null ? null : (string)$v;
    }

    public function getSlug(): ?string
    {
        $v = $this->attributes[static::getSlugName()] ?? null;
        return $v === null ? null : (string)$v;
    }

    public function setSlug(?string $value): void
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
            $slug = $this->getKey() ?: str_random();
        }

        // process slug value or generate it from source
        $slug = $this->strToSlug($slug);

        // check unique
        $original = $slug;
        $num = 0;

        while (!$this->isSlugUnique($slug)) {
            // transform slug until it becomes unique
            $slug = $this->suggestUniqueSlug($original, ++$num, $slug);
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
            if (!$item->slugified && (!$item->getSlug() || $item->isDirty(static::getSlugName()))) {
                $item->setSlug($item->generateSlug());
            }
        });
    }
}