<?php

namespace App\Support;

use App\Models\Production;
use App\Models\ProductionImage;
use App\Models\Story;
use Illuminate\Support\Collection;

/**
 * Pictures that ship with the site, referred to as "archive:godram-10.webp"
 * (public/images/archive) or "gallery:outreach-4731.webp" (public/images/gallery).
 * Anything else is an upload on the private disk.
 */
class SitePicture
{
    public const FOLDERS = ['archive', 'gallery'];

    public static function is(?string $ref): bool
    {
        return $ref !== null && preg_match('/^('.implode('|', self::FOLDERS).'):[\w.-]+$/', $ref) === 1;
    }

    public static function url(string $ref): string
    {
        return asset('images/'.str_replace(':', '/', $ref));
    }

    public static function path(string $ref): string
    {
        return public_path('images/'.str_replace(':', '/', $ref));
    }

    /**
     * Archive photographs not already in use as a showcase cover or photo, a story
     * cover or a page header, so a throwback strip never repeats what is on screen elsewhere.
     */
    public static function unusedArchive(): Collection
    {
        $shown = Production::pluck('cover_path')
            ->merge(ProductionImage::pluck('path'))
            ->merge(Story::pluck('cover_path'))
            ->merge(collect(config('heroes'))->flatten()->map(fn ($p) => str_replace('/', ':', $p)))
            ->filter();

        return collect(config('archive'))->reject(fn ($a) => $shown->contains('archive:'.$a['file']))->values();
    }
}
