<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A question on one candidate's paper, as it was presented to them, with their answer. */
class AttemptQuestion extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $attributes = ['revision' => 0, 'is_flagged' => false, 'seconds_spent' => 0];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array', 'response' => 'json', 'is_flagged' => 'boolean', 'is_correct' => 'boolean',
            'marks_awarded' => 'float', 'first_seen_at' => 'datetime', 'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function isAnswered(): bool
    {
        return ! Question::isBlank($this->response);
    }

    /** What the browser receives during the examination: never the answer or the explanation. */
    public function forCandidate(): array
    {
        $s = $this->snapshot;

        return [
            'position' => $this->position,
            'type' => $s['type'],
            'scenario' => $s['scenario'] ?? null,
            'stem' => $s['stem'],
            'media_kind' => $s['media_kind'] ?? null,
            'media_url' => ($s['media_path'] ?? null) ? route('exams.media', [$this->exam_attempt_id, $this->position]) : null,
            'youtube_id' => $s['youtube_id'] ?? null,
            'options' => $s['options'] ?? null,
            'marks' => $s['marks'],
            'response' => $this->response,
            'flagged' => $this->is_flagged,
            'revision' => $this->revision,
        ];
    }

    /** The candidate's answer in words. */
    public function responseText(): string
    {
        if (! $this->isAnswered()) {
            return 'Not answered';
        }
        $s = $this->snapshot;
        $options = $s['options'] ?? [];
        $text = fn ($key) => collect(isset($options[0]) ? $options : [])->firstWhere('key', $key)['text'] ?? $key;

        return match ($s['type']) {
            'single' => $text($this->response),
            'multiple' => collect((array) $this->response)->map($text)->join('; '),
            'true_false' => filter_var($this->response, FILTER_VALIDATE_BOOLEAN) ? 'True' : 'False',
            'ordering' => collect((array) $this->response)->map($text)->join(' → '),
            'matching' => collect($options['left'] ?? [])->map(fn ($l) => $l['text'].' — '.(collect($options['right'] ?? [])->firstWhere('key', ((array) $this->response)[$l['key']] ?? null)['text'] ?? '?'))->join('; '),
            default => (string) $this->response,
        };
    }
}
