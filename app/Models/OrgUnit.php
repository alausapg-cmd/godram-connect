<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgUnit extends Model
{
    public const NATIONAL = 'national';
    public const REGION = 'region';
    public const DISTRICT = 'district';
    public const ASSEMBLY = 'assembly';

    public const TYPES = [self::NATIONAL, self::REGION, self::DISTRICT, self::ASSEMBLY];

    protected $guarded = ['id', 'path', 'depth'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Keep path and depth in step with the parent so branch queries stay correct.
        static::created(function (OrgUnit $unit) {
            $parentPath = $unit->parent?->path ?? '/';
            $unit->forceFill([
                'path' => $parentPath.$unit->id.'/',
                'depth' => $unit->parent ? $unit->parent->depth + 1 : 0,
            ])->saveQuietly();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id')->orderBy('name');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(MemberPlacement::class);
    }

    public function scopeWithin(Builder $query, OrgUnit $ancestor): Builder
    {
        return $query->where('path', 'like', $ancestor->path.'%');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isWithin(OrgUnit $ancestor): bool
    {
        return str_starts_with($this->path, $ancestor->path);
    }

    /** Ancestor ids from the root down, excluding this unit. */
    public function ancestorIds(): array
    {
        $ids = array_map('intval', array_filter(explode('/', $this->path)));
        array_pop($ids);

        return $ids;
    }

    public function ancestors()
    {
        $ids = $this->ancestorIds();

        return OrgUnit::whereIn('id', $ids)->orderBy('depth')->get();
    }

    /** The nearest ancestor (or self) of a given type. */
    public function ancestorOfType(string $type): ?OrgUnit
    {
        if ($this->type === $type) {
            return $this;
        }

        return OrgUnit::whereIn('id', $this->ancestorIds())->where('type', $type)->first();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::NATIONAL => 'National',
            self::REGION => 'Region',
            self::DISTRICT => 'District',
            self::ASSEMBLY => 'Assembly',
            default => ucfirst($this->type),
        };
    }

    public function childType(): ?string
    {
        return match ($this->type) {
            self::NATIONAL => self::REGION,
            self::REGION => self::DISTRICT,
            self::DISTRICT => self::ASSEMBLY,
            default => null,
        };
    }

    public function fullName(): string
    {
        return in_array($this->type, [self::NATIONAL]) ? $this->name : $this->name.' '.$this->typeLabel();
    }

    public static function root(): ?OrgUnit
    {
        return static::whereNull('parent_id')->first();
    }
}
