<?php

namespace App\Models\Concerns;

use App\Support\SitePicture;
use Illuminate\Support\Facades\Storage;

/**
 * Cover pictures are either uploads (private disk, served by CoverController
 * after a visibility check) or pictures that ship with the site, stored as
 * "archive:godram-10.webp" or "gallery:outreach-4731.webp" (see SitePicture).
 */
trait HasCover
{
    public function coverUrl(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }
        if (SitePicture::is($this->cover_path)) {
            return SitePicture::url($this->cover_path);
        }

        return route('covers.show', [static::COVER_TYPE, $this->getKey(), 'v' => $this->updated_at?->timestamp]);
    }

    /** Absolute path on disk, for share cards. */
    public function coverFile(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return SitePicture::is($this->cover_path)
            ? SitePicture::path($this->cover_path)
            : Storage::disk('local')->path($this->cover_path);
    }
}
