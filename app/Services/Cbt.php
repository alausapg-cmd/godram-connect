<?php

namespace App\Services;

use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Member;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The CBT engine. Builds each candidate's paper from the blueprint, keeps the
 * clock on the server, saves answers as they arrive and marks on the server.
 * Answer keys never leave this class except on review screens after results are released.
 */
class Cbt
{
    public function __construct(
        protected AuditLogger $audit,
        protected Certificates $certificates,
        protected Achievements $achievements,
    ) {}

    /** Resume the attempt in progress, or start a new one if the member is eligible. */
    public function start(Exam $exam, Member $member, ?string $ip = null, ?string $agent = null): ExamAttempt
    {
        $current = $exam->attempts()->where('member_id', $member->id)->where('status', 'in_progress')->latest('id')->first();
        if ($current) {
            if ($current->acceptsAnswers()) {
                $this->event($current, 'resumed', ['ip' => $ip]);

                return $current;
            }
            $this->submit($current, true);
        }

        [$ok, $reason] = $exam->eligibility($member);
        if (! $ok) {
            throw ValidationException::withMessages(['exam' => $reason]);
        }

        $questions = $this->drawPaper($exam);

        return DB::transaction(function () use ($exam, $member, $questions, $ip, $agent) {
            $start = now();
            $deadline = $start->copy()->addMinutes($exam->duration_minutes);
            if ($exam->closes_at && $exam->closes_at->lt($deadline)) {
                $deadline = $exam->closes_at->copy();
            }
            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'member_id' => $member->id,
                'number' => $exam->attemptsUsed($member) + 1,
                'started_at' => $start,
                'deadline_at' => $deadline,
                'max_score' => $questions->sum('marks'),
                'ip' => $ip,
                'user_agent' => $agent ? mb_substr($agent, 0, 255) : null,
            ]);
            foreach ($questions->values() as $i => $q) {
                AttemptQuestion::create([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $q->id,
                    'position' => $i + 1,
                    'snapshot' => $q->snapshot($exam->shuffle_options),
                ]);
            }
            $this->event($attempt, 'started', ['questions' => $questions->count(), 'ip' => $ip]);
            $this->audit->log('exam.started', $exam, $member->full_name.' started attempt '.$attempt->number.' of '.$exam->title, ['attempt' => $attempt->id], $member->assembly());

            return $attempt;
        });
    }

    /** Pick approved questions row by row; a question is never used twice on one paper. */
    public function drawPaper(Exam $exam): Collection
    {
        $rows = $exam->blueprint()->with('category')->get();
        $chosen = collect();
        if ($rows->isEmpty()) {
            $chosen = Question::approved()->inRandomOrder()->limit($exam->question_count)->get();
            if ($chosen->count() < $exam->question_count) {
                throw ValidationException::withMessages(['exam' => 'The question bank does not have enough approved questions for this examination yet.']);
            }
        } else {
            foreach ($rows as $row) {
                $picked = $row->pool()->whereNotIn('id', $chosen->pluck('id')->all() ?: [0])->inRandomOrder()->limit($row->count)->get();
                if ($picked->count() < $row->count) {
                    throw ValidationException::withMessages(['exam' => 'The question bank does not have enough approved '.$row->label().' questions for this examination yet.']);
                }
                $chosen = $chosen->concat($picked);
            }
        }

        return $exam->shuffle_questions ? $chosen->shuffle() : $chosen;
    }

    /** How many approved questions each blueprint row can draw from, for the exam builder. */
    public function poolSizes(Exam $exam): array
    {
        return $exam->blueprint->mapWithKeys(fn ($row) => [$row->id => $row->pool()->count()])->all();
    }

    /**
     * Save one answer. Each save carries a revision number from the browser, so a
     * retried or delayed request can never overwrite a newer answer.
     */
    public function save(ExamAttempt $attempt, int $position, mixed $response, int $revision, ?bool $flagged = null, int $seconds = 0): array
    {
        if ($attempt->status !== 'in_progress') {
            return ['ok' => false, 'reason' => 'submitted'];
        }
        if (! $attempt->acceptsAnswers()) {
            $this->event($attempt, 'late_answer', ['position' => $position]);
            $this->submit($attempt, true);

            return ['ok' => false, 'reason' => 'time'];
        }

        return DB::transaction(function () use ($attempt, $position, $response, $revision, $flagged, $seconds) {
            $item = $attempt->questions()->where('position', $position)->lockForUpdate()->firstOrFail();
            if ($revision <= $item->revision) {
                return ['ok' => true, 'revision' => $item->revision, 'stale' => true];
            }
            $clean = $this->clean($item->snapshot, $response);
            $changed = json_encode($clean) !== json_encode($item->response);
            $item->forceFill([
                'response' => $clean,
                'revision' => $revision,
                'answered_at' => $changed ? now() : $item->answered_at,
                'first_seen_at' => $item->first_seen_at ?? now(),
                'seconds_spent' => $item->seconds_spent + max(0, min($seconds, 900)),
            ]);
            if ($flagged !== null && $flagged !== $item->is_flagged) {
                $item->is_flagged = $flagged;
                $this->event($attempt, $flagged ? 'flagged' : 'unflagged', ['position' => $position]);
            }
            $item->save();
            if ($changed) {
                $this->event($attempt, 'answered', ['position' => $position, 'revision' => $revision]);
            }

            return ['ok' => true, 'revision' => $revision];
        });
    }

    /** Reject anything that is not a possible answer to this question. */
    public function clean(array $snapshot, mixed $response): mixed
    {
        if (Question::isBlank($response)) {
            return null;
        }
        $options = $snapshot['options'] ?? [];
        $keys = fn () => collect(isset($options[0]) ? $options : [])->pluck('key')->map(fn ($k) => (string) $k)->all();
        $bad = fn () => throw ValidationException::withMessages(['response' => 'That answer could not be saved. Please choose again.']);

        switch ($snapshot['type']) {
            case 'single':
                in_array((string) $response, $keys(), true) || $bad();

                return (string) $response;
            case 'multiple':
                $given = array_values(array_unique(array_map('strval', (array) $response)));
                array_diff($given, $keys()) === [] || $bad();

                return $given;
            case 'true_false':
                in_array($response, [true, false, 'true', 'false', 1, 0, '1', '0'], true) || $bad();

                return filter_var($response, FILTER_VALIDATE_BOOLEAN);
            case 'ordering':
                $given = array_values(array_map('strval', (array) $response));
                $sorted = $given;
                $all = $keys();
                sort($sorted);
                sort($all);
                $sorted === $all || $bad();

                return $given;
            case 'matching':
                $left = collect($options['left'] ?? [])->pluck('key')->map(fn ($k) => (string) $k)->all();
                $right = collect($options['right'] ?? [])->pluck('key')->map(fn ($k) => (string) $k)->all();
                $clean = [];
                foreach ((array) $response as $l => $r) {
                    if ($r === null || $r === '') {
                        continue;
                    }
                    (in_array((string) $l, $left, true) && in_array((string) $r, $right, true)) || $bad();
                    $clean[(string) $l] = (string) $r;
                }

                return $clean ?: null;
            default:
                is_scalar($response) || $bad();

                return mb_substr(trim((string) $response), 0, $snapshot['type'] === 'open' ? 5000 : 300);
        }
    }

    /** Lost connection, came back, or left the examination screen. */
    public function signal(ExamAttempt $attempt, string $type, array $data = []): void
    {
        if (! in_array($type, ['offline', 'online', 'focus_lost', 'presented'], true) || $attempt->status !== 'in_progress') {
            return;
        }
        if ($type === 'presented') {
            $attempt->questions()->where('position', (int) ($data['position'] ?? 0))->whereNull('first_seen_at')->update(['first_seen_at' => now()]);

            return;
        }
        if ($type === 'offline') {
            $attempt->increment('disconnections');
        }
        if ($type === 'focus_lost') {
            $attempt->increment('focus_losses');
        }
        $this->event($attempt, $type, $data);
    }

    /** Mark the paper on the server and record the result. Safe to call twice. */
    public function submit(ExamAttempt $attempt, bool $auto = false): ExamAttempt
    {
        $finished = DB::transaction(function () use ($attempt, $auto) {
            $attempt = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if ($attempt->isFinished()) {
                return null;
            }
            foreach ($attempt->questions as $item) {
                [$correct, $marks] = Question::grade($item->snapshot, $item->response);
                $item->forceFill(['is_correct' => $correct, 'marks_awarded' => $marks])->save();
            }
            $end = now()->min($attempt->deadline_at);
            $attempt->forceFill([
                'status' => $auto ? 'auto_submitted' : 'submitted',
                'submitted_at' => now(),
                'time_used_seconds' => max(0, (int) $attempt->started_at->diffInSeconds($end)),
            ]);
            $this->tally($attempt);
            $this->event($attempt, $auto ? 'auto_submitted' : 'submitted');
            $this->event($attempt, 'result', ['score' => $attempt->score, 'percent' => $attempt->percent, 'passed' => $attempt->passed]);
            $this->audit->log($auto ? 'exam.auto_submitted' : 'exam.submitted', $attempt->exam, $attempt->member->full_name.' finished attempt '.$attempt->number.' of '.$attempt->exam->title.($attempt->needs_marking ? ' (written answers to mark)' : ': '.$attempt->percent.'%'), ['attempt' => $attempt->id], $attempt->member->assembly());

            return $attempt;
        });

        if ($finished) {
            $this->afterResult($finished);

            return $finished;
        }

        return $attempt->fresh();
    }

    /** Recalculate totals from the marks on each question. */
    public function tally(ExamAttempt $attempt): void
    {
        $items = $attempt->questions()->get();
        $unmarked = $items->filter(fn ($i) => $i->marks_awarded === null && $i->isAnswered());
        $score = round($items->sum(fn ($i) => (float) $i->marks_awarded), 2);
        $max = (float) $items->sum(fn ($i) => $i->snapshot['marks']);
        $percent = $max > 0 ? round($score / $max * 100, 2) : 0;
        $attempt->forceFill([
            'score' => $score,
            'max_score' => $max,
            'percent' => $unmarked->isEmpty() ? $percent : null,
            'passed' => $unmarked->isEmpty() ? $percent >= $attempt->exam->pass_mark : null,
            'needs_marking' => $unmarked->isNotEmpty(),
            'correct' => $items->where('is_correct', true)->count(),
            'incorrect' => $items->filter(fn ($i) => $i->isAnswered() && $i->is_correct === false)->count(),
            'unanswered' => $items->reject->isAnswered()->count(),
        ])->save();
    }

    /** An examiner marks a written answer. */
    public function mark(AttemptQuestion $item, float $marks, User $by): void
    {
        $full = (float) $item->snapshot['marks'];
        $marks = max(0, min($full, round($marks, 2)));
        $attempt = $item->attempt;
        DB::transaction(function () use ($item, $marks, $full, $by, $attempt) {
            $item->forceFill(['marks_awarded' => $marks, 'is_correct' => $marks >= $full, 'marked_by' => $by->id])->save();
            $this->tally($attempt);
            $this->event($attempt, 'marked', ['position' => $item->position, 'marks' => $marks], $by);
            $this->audit->log('exam.marked', $attempt->exam, 'Question '.$item->position.' of '.$attempt->member->full_name.'\'s attempt marked '.$marks.' of '.$full, ['attempt' => $attempt->id]);
        });
        if (! $attempt->fresh()->needs_marking) {
            $this->afterResult($attempt->fresh());
        }
    }

    /** Extra time for one candidate, for example for a disability or a power cut. */
    public function extend(ExamAttempt $attempt, int $minutes, string $reason, User $by): void
    {
        $attempt->forceFill(['deadline_at' => $attempt->deadline_at->copy()->addMinutes($minutes)])->save();
        $this->event($attempt, 'extended', ['minutes' => $minutes, 'reason' => $reason], $by);
        $this->audit->log('exam.time_extended', $attempt->exam, $attempt->member->full_name.' given '.$minutes.' more minutes: '.$reason, ['attempt' => $attempt->id]);
    }

    /** Submit attempts whose time ran out while the candidate was offline. Runs every minute. */
    public function finaliseExpired(): int
    {
        $count = 0;
        ExamAttempt::where('status', 'in_progress')
            ->where('deadline_at', '<', now()->subSeconds(ExamAttempt::GRACE_SECONDS))
            ->each(function (ExamAttempt $attempt) use (&$count) {
                $this->submit($attempt, true);
                $count++;
            });

        return $count;
    }

    /** Release results held back for an exam, and issue the certificates they earn. */
    public function release(Exam $exam, User $by): int
    {
        $exam->forceFill(['released_at' => now()])->save();
        $this->audit->log('exam.results_released', $exam, 'Results released for '.$exam->title, [], $exam->orgUnit, $by->id);

        return $exam->attempts()->select('member_id')->distinct()->pluck('member_id')
            ->filter(fn ($id) => $this->certificates->forExam($exam, Member::find($id), $by))->count();
    }

    protected function afterResult(ExamAttempt $attempt): void
    {
        $exam = $attempt->exam;
        if ($exam->resultsReleased()) {
            $certificate = $this->certificates->forExam($exam, $attempt->member);
            if ($certificate) {
                $this->event($attempt, 'certificate', ['number' => $certificate->number]);
            }
        }
        $this->achievements->evaluate($attempt->member);
    }

    public function event(ExamAttempt $attempt, string $type, array $data = [], ?User $by = null): void
    {
        AttemptEvent::create([
            'exam_attempt_id' => $attempt->id,
            'type' => $type,
            'data' => $data ?: null,
            'user_id' => $by?->id ?? auth()->id(),
            'created_at' => now(),
        ]);
    }
}
