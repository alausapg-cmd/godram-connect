<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One sitting of an examination by one member. The server keeps the clock. */
class ExamAttempt extends Model
{
    /** Seconds after the deadline in which answers already on their way are still accepted. */
    public const GRACE_SECONDS = 30;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'in_progress', 'needs_marking' => false, 'correct' => 0, 'incorrect' => 0, 'unanswered' => 0, 'disconnections' => 0, 'focus_losses' => 0];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'deadline_at' => 'datetime', 'submitted_at' => 'datetime',
            'score' => 'float', 'max_score' => 'float', 'percent' => 'float',
            'passed' => 'boolean', 'needs_marking' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AttemptQuestion::class)->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttemptEvent::class)->orderBy('id');
    }

    public function isFinished(): bool
    {
        return $this->status !== 'in_progress';
    }

    /** True while the candidate may still change answers. */
    public function acceptsAnswers(): bool
    {
        return $this->status === 'in_progress' && now()->lte($this->deadline_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    public function secondsLeft(): int
    {
        return max(0, (int) floor(now()->diffInSeconds($this->deadline_at, false)));
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'in_progress' => 'In progress',
            $this->needs_marking => 'Waiting for marking',
            $this->passed === true => 'Passed',
            $this->passed === false => 'Not passed',
            default => 'Submitted',
        };
    }

    public function timeUsedLabel(): string
    {
        $s = (int) $this->time_used_seconds;

        return intdiv($s, 60).' min '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).' s';
    }

    /** Signs an examiner may want to look at. Not proof of anything on their own. */
    public function concerns(): array
    {
        return array_values(array_filter([
            $this->focus_losses >= 3 ? 'Left the examination screen '.$this->focus_losses.' times' : null,
            $this->disconnections >= 3 ? 'Lost connection '.$this->disconnections.' times' : null,
            $this->time_used_seconds !== null && $this->time_used_seconds < 8 * $this->questions()->count() ? 'Finished unusually quickly' : null,
        ]));
    }
}
