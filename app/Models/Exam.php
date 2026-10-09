<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use App\Services\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** A CBT examination, drawn from the question bank by its blueprint. */
class Exam extends Model
{
    use HasSlug;

    public const MODES = ['practice' => 'Practice', 'certification' => 'Certification'];

    public const POLICIES = ['best' => 'Best score', 'latest' => 'Latest score', 'average' => 'Average score'];

    public const RELEASES = [
        'immediate' => 'As soon as the candidate submits',
        'after_close' => 'When the examination closes',
        'manual' => 'When an administrator releases them',
    ];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

    protected $guarded = ['id', 'slug'];

    /** Same defaults as the table, so a new exam behaves the same before and after it is reloaded. */
    protected $attributes = [
        'mode' => 'certification', 'duration_minutes' => 30, 'question_count' => 20, 'pass_mark' => 70,
        'result_policy' => 'best', 'shuffle_questions' => true, 'shuffle_options' => true, 'release' => 'immediate',
        'show_review' => false, 'awards_certificate' => false, 'requires_course_completion' => false, 'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime', 'closes_at' => 'datetime', 'released_at' => 'datetime',
            'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean', 'show_review' => 'boolean',
            'awards_certificate' => 'boolean', 'requires_course_completion' => 'boolean', 'is_demo' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function blueprint(): HasMany
    {
        return $this->hasMany(ExamBlueprintRow::class)->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Published examinations a member may sit: those of their courses, and open ones for their part of the tree. */
    public function scopeAvailableTo(Builder $query, ?User $user): Builder
    {
        $query->published();
        $member = $user?->member;
        if (! $member) {
            return $query->whereRaw('1 = 0');
        }
        $assembly = $member->assembly();
        $unitIds = $assembly ? array_merge($assembly->ancestorIds(), [$assembly->id]) : [0];
        $courseIds = Enrolment::where('member_id', $member->id)->where('status', '!=', 'withdrawn')->pluck('course_id')->all();

        return $query->where(fn ($q) => $q
            ->whereIn('course_id', $courseIds ?: [0])
            ->orWhere(fn ($q) => $q->whereNull('course_id')->whereIn('org_unit_id', $unitIds)));
    }

    public function canBeManagedBy(?User $user): bool
    {
        return $user && $this->orgUnit && app(Access::class)->can($user, 'exams.manage', $this->orgUnit);
    }

    public function isPractice(): bool
    {
        return $this->mode === 'practice';
    }

    public function hasOpened(): bool
    {
        return ! $this->opens_at || $this->opens_at->isPast();
    }

    public function hasClosed(): bool
    {
        return $this->closes_at && $this->closes_at->isPast();
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && $this->hasOpened() && ! $this->hasClosed();
    }

    /** Whether candidates can see their scores yet. */
    public function resultsReleased(): bool
    {
        return match ($this->release) {
            'immediate' => true,
            'after_close' => $this->hasClosed() || $this->released_at !== null,
            default => $this->released_at !== null,
        };
    }

    public function plannedCount(): int
    {
        $rows = $this->relationLoaded('blueprint') ? $this->blueprint : $this->blueprint()->get();

        return $rows->isEmpty() ? (int) $this->question_count : (int) $rows->sum('count');
    }

    /** @return array{0: bool, 1: ?string} whether the member may start, and why not. */
    public function eligibility(?Member $member): array
    {
        if (! $member) {
            return [false, 'Sign in with your member account to take this examination.'];
        }
        if ($this->status !== 'published') {
            return [false, 'This examination is not open.'];
        }
        if (! $this->hasOpened()) {
            return [false, 'This examination opens '.$this->opens_at->format('l j F Y \a\t g:ia').'.'];
        }
        if ($this->hasClosed()) {
            return [false, 'This examination closed '.$this->closes_at->format('j F Y \a\t g:ia').'.'];
        }
        if ($this->course_id) {
            $enrolment = Enrolment::where('course_id', $this->course_id)->where('member_id', $member->id)->where('status', '!=', 'withdrawn')->first();
            if (! $enrolment) {
                return [false, 'Enrol in '.$this->course->title.' to take this examination.'];
            }
            if ($this->requires_course_completion && $enrolment->status !== 'completed') {
                return [false, 'Complete every lesson of '.$this->course->title.' first.'];
            }
        } else {
            $assembly = $member->assembly();
            if (! $assembly || ! $assembly->isWithin($this->orgUnit)) {
                return [false, 'This examination is for members in '.$this->orgUnit->fullName().'.'];
            }
        }
        if ($this->max_attempts && $this->attemptsUsed($member) >= $this->max_attempts) {
            return [false, 'You have used all your attempts.'];
        }

        return [true, null];
    }

    public function attemptsFor(Member $member): Collection
    {
        return $this->attempts()->where('member_id', $member->id)->orderBy('number')->get();
    }

    public function attemptsUsed(Member $member): int
    {
        return $this->attempts()->where('member_id', $member->id)->count();
    }

    public function attemptsLeft(Member $member): ?int
    {
        return $this->max_attempts ? max(0, $this->max_attempts - $this->attemptsUsed($member)) : null;
    }

    /**
     * The member's result under the exam's policy, from finished and fully marked attempts.
     *
     * @return ?object{percent: float, passed: bool, attempt: ExamAttempt, attempts: int}
     */
    public function resultFor(Member $member, ?Collection $attempts = null): ?object
    {
        $attempts = ($attempts ?? $this->attemptsFor($member))->filter(fn (ExamAttempt $a) => $a->isFinished() && ! $a->needs_marking && $a->percent !== null)->values();
        if ($attempts->isEmpty()) {
            return null;
        }
        [$percent, $attempt] = match ($this->result_policy) {
            'latest' => [(float) $attempts->last()->percent, $attempts->last()],
            'average' => [round((float) $attempts->avg('percent'), 2), $attempts->sortByDesc('percent')->first()],
            default => [(float) $attempts->max('percent'), $attempts->sortByDesc('percent')->first()],
        };

        return (object) ['percent' => $percent, 'passed' => $percent >= $this->pass_mark, 'attempt' => $attempt, 'attempts' => $attempts->count()];
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? ucfirst($this->mode);
    }

    public function scopeLabel(): string
    {
        return $this->course ? $this->course->title : 'Members in '.$this->orgUnit?->fullName();
    }

    public function windowLabel(): string
    {
        if (! $this->opens_at && ! $this->closes_at) {
            return 'Open at any time';
        }
        if (! $this->closes_at) {
            return 'Opens '.$this->opens_at->format('j M Y, g:ia');
        }

        return ($this->opens_at ? $this->opens_at->format('j M, g:ia').' to ' : 'Until ').$this->closes_at->format('j M Y, g:ia');
    }
}
