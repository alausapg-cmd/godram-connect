<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Practical work: scene writing, a monologue, a recorded performance exercise. */
class Assignment extends Model
{
    public const ACCEPTS = [
        'text' => 'Written answer',
        'file' => 'File (document, image or audio)',
        'link' => 'Video link (YouTube, Google Drive or Facebook)',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['accepts' => 'array', 'due_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function accepts(string $what): bool
    {
        return in_array($what, (array) $this->accepts, true);
    }

    public function briefHtml(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->brief, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast();
    }
}
