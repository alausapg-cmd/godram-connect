<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventRegistration extends Model
{
    protected $guarded = ['id', 'reference'];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (EventRegistration $r) {
            do {
                $r->reference = 'EVT-'.Str::upper(Str::random(6));
            } while (static::where('reference', $r->reference)->exists());
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
