<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A download (PDF, Word, PowerPoint, script, worksheet, audio) or a link. */
class CourseResource extends Model
{
    protected $guarded = ['id'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isLink(): bool
    {
        return $this->url !== null && $this->path === null;
    }

    public function href(): string
    {
        return $this->isLink() ? $this->url : route('academy.resource', [$this->course, $this]);
    }

    public function typeLabel(): string
    {
        if ($this->isLink()) {
            return 'Link';
        }
        $ext = strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'PDF',
            'doc', 'docx' => 'Word',
            'ppt', 'pptx' => 'PowerPoint',
            'mp3', 'm4a', 'ogg', 'wav', 'aac' => 'Audio',
            'jpg', 'jpeg', 'png', 'webp' => 'Image',
            default => strtoupper($ext ?: 'File'),
        };
    }

    public function sizeLabel(): ?string
    {
        if (! $this->size) {
            return null;
        }

        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }
}
