<?php

namespace App\Models\Concerns;

/**
 * Cover pictures are either uploads (private disk, served by CoverController
 * after a visibility check) or images from the public GODRAM archive,
 * stored as "archive:godram-10.webp".
 */
trait HasCover
{
    public function coverUrl(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }
        if (str_starts_with($this->cover_path, 'archive:')) {
            return asset('images/archive/'.substr($this->cover_path, 8));
        }

        return route('covers.show', [static::COVER_TYPE, $this->getKey(), 'v' => $this->updated_at?->timestamp]);
    }

    /** Absolute path on disk, for share cards. */
    public function coverFile(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return str_starts_with($this->cover_path, 'archive:')
            ? public_path('images/archive/'.substr($this->cover_path, 8))
            : \Illuminate\Support\Facades\Storage::disk('local')->path($this->cover_path);
    }
}
