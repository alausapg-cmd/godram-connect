<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The CBT audit trail: started, answer saved, flagged, connection lost, submitted and so on. */
class AttemptEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'started' => 'Attempt started',
        'resumed' => 'Returned to the examination',
        'presented' => 'Question presented',
        'answered' => 'Answer saved',
        'flagged' => 'Question flagged',
        'unflagged' => 'Flag removed',
        'offline' => 'Connection lost',
        'online' => 'Reconnected',
        'focus_lost' => 'Left the examination screen',
        'late_answer' => 'Answer arrived after time',
        'submitted' => 'Submitted',
        'auto_submitted' => 'Submitted automatically when time ran out',
        'result' => 'Result generated',
        'marked' => 'Written answer marked',
        'extended' => 'Time extended',
        'certificate' => 'Certificate generated',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
