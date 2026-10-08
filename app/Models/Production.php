<?php

namespace App\Models;

use App\Models\Concerns\HasCover;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    use HasCover, HasSlug;

    public const COVER_TYPE = 'production';

    public const KINDS = [
        'stage_play' => 'Stage play',
        'film' => 'Film',
        'short_film' => 'Short film',
        'drama' => 'Drama performance',
        'poster' => 'Poster',
        'photography' => 'Photography',
        'creative_project' => 'Creative project',
        'award' => 'Award',
        'achievement' => 'Achievement',
        'major_event' => 'Major event',
    ];

    protected $guarded = ['id', 'slug'];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'is_demo' => 'boolean', 'published_at' => 'datetime'];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductionImage::class)->orderBy('sort')->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class)->where('status', 'published');
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class)->where('status', 'published');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst(str_replace('_', ' ', $this->kind));
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->status === 'published' || app(\App\Services\Access::class)->can($user, 'media.manage');
    }
}
