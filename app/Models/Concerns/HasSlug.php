<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** Readable, unique URLs such as /stories/majemu-covenant-1994. */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (blank($model->slug)) {
                $base = Str::slug(Str::limit($model->title, 70, '')) ?: 'item';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $model->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
