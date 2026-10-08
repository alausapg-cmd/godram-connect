<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Recurring features such as Performance of the Week and Creative Spotlight. */
class Spotlight extends Model
{
    public const KINDS = [
        'performance_of_week' => 'Performance of the Week',
        'creative_spotlight' => 'Creative Spotlight',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCurrent(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind)
            ->whereDate('starts_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
            ->latest('starts_on')->latest('id');
    }

    public static function currentSubject(string $kind): ?Model
    {
        $spotlight = static::current($kind)->with('subject')->get()
            ->first(fn ($s) => $s->subject && ($s->subject->status ?? 'published') === 'published');

        return $spotlight?->subject?->setAttribute('spotlight_note', $spotlight->note);
    }
}
