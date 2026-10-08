<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrolment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    public function completedLessonIds(): array
    {
        return ($this->relationLoaded('progress') ? $this->progress : $this->progress()->get())->pluck('lesson_id')->all();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Lessons completed',
            'withdrawn' => 'Withdrawn',
            default => 'In progress',
        };
    }
}
