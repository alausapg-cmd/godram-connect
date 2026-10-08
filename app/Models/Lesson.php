<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Lesson extends Model
{
    public const KINDS = [
        'video' => 'Video lesson',
        'audio' => 'Audio lesson',
        'text' => 'Reading',
        'document' => 'Worksheet or script',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_preview' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(CourseResource::class)->orderBy('sort')->orderBy('id');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class)->orderBy('sort')->orderBy('id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    public function keyPointList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->key_points))));
    }

    public function icon(): string
    {
        return match ($this->kind) {
            'video' => 'play',
            'audio' => 'headphones',
            'document' => 'file',
            default => 'book',
        };
    }
}
