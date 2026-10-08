<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionImage extends Model
{
    public const COVER_TYPE = 'production-image';

    protected $guarded = ['id'];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function url(): string
    {
        return str_starts_with($this->path, 'archive:')
            ? asset('images/archive/'.substr($this->path, 8))
            : route('covers.show', [self::COVER_TYPE, $this->id]);
    }
}
