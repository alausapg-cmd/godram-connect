<?php

namespace App\Models;

use App\Support\SitePicture;
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
        return SitePicture::is($this->path)
            ? SitePicture::url($this->path)
            : route('covers.show', [self::COVER_TYPE, $this->id]);
    }
}
