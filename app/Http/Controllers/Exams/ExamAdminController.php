<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AttemptQuestion;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\Cbt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Build examinations, follow candidates, mark written answers and read the analytics. */
class ExamAdminController extends Controller
{
    public function __construct(protected Access $access, protected Cbt $cbt, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $paths = $this->paths($request);
        $exams = Exam::with('course', 'orgUnit')
            ->where(fn ($q) => $this->withinPaths($q, $paths))
            ->withCount(['attempts', 'attempts as finished_count' => fn ($q) => $q->where('status', '!=', 'in_progress'),
                'attempts as marking_count' => fn ($q) => $q->where('needs_marking', true)])
            ->orderByRaw("case status when 'published' then 0 when 'draft' then 1 else 2 end")->latest('id')->get();

        return view('exams.manage.index', [
            'exams' => $exams,
            'bank' => ['approved' => Question::approved()->count(), 'waiting' => Question::where('status', 'draft')->count()],
        ]);
    }

    public function create(Request $request)
    {
        $this->paths($request);
        $course = $request->query('course') ? Course::where('slug', $request->query('course'))->first() : null;
        $exam = new Exam([
            'mode' => 'certification', 'duration_minutes' => 30, 'question_count' => 20, 'pass_mark' => 70, 'max_attempts' => 2,
            'result_policy' => 'best', 'shuffle_questions' => true, 'shuffle_options' => true, 'release' => 'immediate',
            'awards_certificate' => true, 'requires_course_completion' => (bool) $course, 'course_id' => $course?->id,
            'org_unit_id' => $course?->org_unit_id, 'title' => $course ? $course->title.': Final examination' : null,
        ]);

        return view('exams.manage.form', $this->formData($request, $exam));
    }

    public function store(Request $request)
    {
        $this->paths($request);
        $exam = DB::transaction(function () use ($request) {
            $exam = new Exam(['created_by' => $request->user()->id, 'status' => 'draft']);
            $this->fill($exam, $request);
            $exam->save();
            $this->saveBlueprint($exam, $request);
            $this->audit->log('exam.created', $exam, 'Examination created: '.$exam->title, [], $exam->orgUnit);

            return $exam;
        });

        return redirect()->route('exams.manage.edit', $exam)->with('status', 'Examination saved as a draft. Check the question pool, then publish it.');
    }

    public function edit(Request $request, Exam $exam)
    {
        $this->authorise($request, $exam);

        return view('exams.manage.form', $this->formData($request, $exam->load('blueprint.category')) + ['pools' => $this->cbt->poolSizes($exam)]);
    }

    public function update(Request $request, Exam $exam)
    {
        $this->authorise($request, $exam);
        DB::transaction(function () use ($request, $exam) {
            $this->fill($exam, $request);
            $exam->save();
            $this->saveBlueprint($exam, $request);
            $this->audit->log('exam.updated', $exam, 'Examination settings changed: '.$exam->title, [], $exam->orgUnit);
        });

        return redirect()->route('exams.manage.edit', $exam)->with('status', 'Examination saved.'.($exam->attempts()->exists() ? ' Papers already started keep the questions they were given.' : ''));
    }

    public function status(Request $request, Exam $exam)
    {
        $this->authorise($request, $exam);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Exam::STATUSES))]]);
        if ($data['status'] === 'published') {
            try {
                $this->cbt->drawPaper($exam);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
            }
        }
        $exam->update($data);
        $this->audit->log('exam.'.$data['status'], $exam, $exam->title.': '.Exam::STATUSES[$data['status']], [], $exam->orgUnit);

        return back()->with('status', $data['status'] === 'published' ? 'Published. Eligible members can now see it under My examinations.' : 'Saved.');
    }

    public function release(Request $request, Exam $exam)
    {
        $this->authorise($request, $exam);
        $issued = $this->cbt->release($exam, $request->user());

        return back()->with('status', 'Results released.'.($issued ? ' '.$issued.' '.str('certificate')->plural($issued).' issued.' : ''));
    }

    /** Candidates, scores and question performance for the people in the viewer's area. */
    public function show(Request $request, Exam $exam)
    {
        $this->authorise($request, $exam);
        $paths = $this->paths($request);
        $filters = $request->only(['unit', 'result', 'attempt', 'from', 'to']);
        $unit = ($filters['unit'] ?? null) ? OrgUnit::find($filters['unit']) : null;
        if ($unit && collect($paths)->contains(fn ($p) => str_starts_with($unit->path, $p))) {
            $paths = [$unit->path];
        }

        $attempts = $exam->attempts()->whereHas('member', fn ($q) => $q->placedWithinPaths($paths))
            ->with('member.currentPlacement.orgUnit.parent.parent')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '<=', $v))
            ->orderBy('member_id')->orderBy('number')->get();
        if (($filters['attempt'] ?? null) === 'first') {
            $attempts = $attempts->where('number', 1);
        } elseif (($filters['attempt'] ?? null) === 'latest') {
            $attempts = $attempts->groupBy('member_id')->map->last()->values();
        }
        $attempts = match ($filters['result'] ?? null) {
            'passed' => $attempts->where('passed', true),
            'failed' => $attempts->where('passed', false),
            'marking' => $attempts->where('needs_marking', true),
            'in_progress' => $attempts->where('status', 'in_progress'),
            default => $attempts,
        };
        $finished = $attempts->filter(fn ($a) => $a->isFinished() && $a->percent !== null);
        $members = $attempts->pluck('member_id')->unique();
        $results = $members->map(fn ($id) => $exam->resultFor($attempts->firstWhere('member_id', $id)->member, $attempts->where('member_id', $id)))->filter();

        $units = $this->access->unitsWithin($request->user(), 'exams.manage')->whereIn('type', [OrgUnit::REGION, OrgUnit::DISTRICT, OrgUnit::ASSEMBLY]);

        if ($request->input('format') === 'csv') {
            return $this->csv($exam, $attempts);
        }

        return view('exams.manage.show', [
            'exam' => $exam->load('course', 'orgUnit', 'blueprint.category'),
            'attempts' => $attempts,
            'filters' => $filters,
            'units' => $units,
            'stats' => [
                'registered' => $this->candidateCount($exam, $this->paths($request)),
                'started' => $members->count(),
                'completed' => $attempts->filter->isFinished()->pluck('member_id')->unique()->count(),
                'in_progress' => $attempts->where('status', 'in_progress')->count(),
                'marking' => $attempts->where('needs_marking', true)->count(),
                'average' => $finished->isEmpty() ? null : round($finished->avg('percent'), 1),
                'highest' => $finished->max('percent'),
                'lowest' => $finished->min('percent'),
                'pass_rate' => $results->isEmpty() ? null : round($results->where('passed', true)->count() / $results->count() * 100),
            ],
            'questions' => $this->questionStats($attempts->pluck('id')),
        ]);
    }

    public function attempt(Request $request, Exam $exam, ExamAttempt $attempt)
    {
        $this->authorise($request, $exam);
        abort_unless(Member::whereKey($attempt->member_id)->placedWithinPaths($this->paths($request))->exists(), 404);

        return view('exams.manage.attempt', [
            'exam' => $exam,
            'attempt' => $attempt->load('questions.marker', 'events.user', 'member.currentPlacement.orgUnit'),
            'others' => $exam->attempts()->where('member_id', $attempt->member_id)->orderBy('number')->get(),
        ]);
    }

    public function mark(Request $request, Exam $exam, ExamAttempt $attempt)
    {
        $this->authorise($request, $exam);
        $data = $request->validate(['marks' => ['required', 'array'], 'marks.*' => ['nullable', 'numeric', 'min:0']]);
        foreach ($data['marks'] as $position => $marks) {
            $item = $attempt->questions()->where('position', $position)->first();
            if ($item && $marks !== null && $item->snapshot['type'] === 'open') {
                $this->cbt->mark($item, (float) $marks, $request->user());
            }
        }

        return back()->with('status', 'Marks saved.'.($attempt->fresh()->needs_marking ? '' : ' The result is complete.'));
    }

    public function extend(Request $request, Exam $exam, ExamAttempt $attempt)
    {
        $this->authorise($request, $exam);
        $data = $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:240'], 'reason' => ['required', 'string', 'max:200']]);
        abort_unless($attempt->status === 'in_progress', 422, 'This attempt has already been submitted.');
        $this->cbt->extend($attempt, $data['minutes'], $data['reason'], $request->user());

        return back()->with('status', 'Time extended by '.$data['minutes'].' minutes.');
    }

    /** Times presented, correct, skipped, average time and how often each wrong choice was picked. */
    protected function questionStats(Collection $attemptIds): Collection
    {
        if ($attemptIds->isEmpty()) {
            return collect();
        }
        $items = AttemptQuestion::whereIn('exam_attempt_id', $attemptIds)->whereHas('attempt', fn ($q) => $q->where('status', '!=', 'in_progress'))->get();

        return $items->groupBy('question_id')->map(function (Collection $uses, $id) {
            $latest = $uses->sortByDesc(fn ($u) => $u->snapshot['version'] ?? 1)->first()->snapshot;
            $n = $uses->count();
            $answered = $uses->filter->isAnswered();
            $correct = $uses->where('is_correct', true)->count();
            $choices = collect();
            if (in_array($latest['type'], ['single', 'multiple'], true)) {
                $picked = $answered->flatMap(fn ($u) => (array) $u->response)->countBy();
                $correctKeys = $latest['type'] === 'single' ? [$latest['answer']['key'] ?? null] : ($latest['answer']['keys'] ?? []);
                $choices = collect($latest['options'])->sortBy('key')->map(fn ($o) => (object) [
                    'text' => $o['text'], 'count' => $picked[$o['key']] ?? 0, 'correct' => in_array($o['key'], $correctKeys, true),
                ])->values();
            }
            $rate = $n ? $correct / $n : 0;
            $strongDistractor = $choices->where('correct', false)->max('count') > $choices->where('correct', true)->max('count');
            $flags = array_values(array_filter([
                $n >= 5 && $rate < 0.2 ? 'Very few answer this correctly' : null,
                $n >= 5 && $rate > 0.95 ? 'Almost everyone answers correctly' : null,
                $n >= 5 && $strongDistractor ? 'A wrong choice is picked more than the right one' : null,
                $n >= 5 && ($n - $answered->count()) / $n > 0.3 ? 'Often skipped' : null,
            ]));

            return (object) [
                'id' => $id,
                'stem' => $latest['stem'],
                'type' => $latest['type'],
                'presented' => $n,
                'correct' => $n ? round($correct / $n * 100) : 0,
                'incorrect' => $n ? round(($answered->count() - $correct) / $n * 100) : 0,
                'skipped' => $n ? round(($n - $answered->count()) / $n * 100) : 0,
                'seconds' => (int) round($uses->avg('seconds_spent')),
                'choices' => $choices,
                'flags' => $flags,
            ];
        })->sortByDesc(fn ($q) => count($q->flags))->values();
    }

    protected function candidateCount(Exam $exam, array $paths): int
    {
        if ($exam->course_id) {
            return Enrolment::where('course_id', $exam->course_id)->where('status', '!=', 'withdrawn')
                ->when($exam->requires_course_completion, fn ($q) => $q->where('status', 'completed'))
                ->whereHas('member', fn ($q) => $q->placedWithinPaths($paths))->count();
        }

        // Members both under the examining unit and in the viewer's area.
        $examPath = $exam->orgUnit->path;
        $overlap = collect($paths)->map(fn ($p) => str_starts_with($examPath, $p) ? $examPath : (str_starts_with($p, $examPath) ? $p : null))->filter()->unique()->values()->all();

        return Member::where('status', 'active')->whereNotNull('approved_at')->placedWithinPaths($overlap)->count();
    }

    protected function csv(Exam $exam, Collection $attempts)
    {
        return response()->streamDownload(function () use ($attempts) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Member ID', 'Name', 'Assembly', 'District', 'Region', 'Attempt', 'Status', 'Score', 'Out of', 'Percent', 'Result', 'Correct', 'Incorrect', 'Unanswered', 'Time used (min)', 'Started', 'Submitted']);
            foreach ($attempts as $a) {
                $assembly = $a->member->currentPlacement?->orgUnit;
                fputcsv($out, [
                    $a->member->member_no, $a->member->full_name, $assembly?->name, $assembly?->parent?->name, $assembly?->parent?->parent?->name,
                    $a->number, $a->statusLabel(), $a->score, $a->max_score, $a->percent, $a->passed === null ? '' : ($a->passed ? 'Pass' : 'Not passed'),
                    $a->correct, $a->incorrect, $a->unanswered, $a->time_used_seconds ? round($a->time_used_seconds / 60, 1) : '',
                    $a->started_at?->format('Y-m-d H:i'), $a->submitted_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $exam->slug.'-results.csv', ['Content-Type' => 'text/csv']);
    }

    protected function formData(Request $request, Exam $exam): array
    {
        $units = $this->access->unitsWithin($request->user(), 'exams.manage')->where('type', '!=', OrgUnit::ASSEMBLY)->values();
        $courses = Course::whereIn('org_unit_id', $this->access->unitsWithin($request->user(), 'exams.manage')->pluck('id'))
            ->where('status', '!=', 'archived')->orderBy('title')->get();

        return [
            'exam' => $exam,
            'units' => $units,
            'courses' => $courses,
            'categories' => QuestionCategory::orderBy('sort')->orderBy('name')->get()->map(function ($c) {
                $c->pool = Question::approved()->where('question_category_id', $c->id)->selectRaw('difficulty, count(*) as n')->groupBy('difficulty')->pluck('n', 'difficulty');

                return $c;
            }),
            'approved' => Question::approved()->count(),
            'pools' => [],
        ];
    }

    protected function fill(Exam $exam, Request $request): void
    {
        $unitIds = $this->access->unitsWithin($request->user(), 'exams.manage')->pluck('id')->all();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'org_unit_id' => ['required', Rule::in($unitIds)],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'mode' => ['required', Rule::in(array_keys(Exam::MODES))],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'question_count' => ['required', 'integer', 'min:1', 'max:300'],
            'pass_mark' => ['required', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:50'],
            'result_policy' => ['required', Rule::in(array_keys(Exam::POLICIES))],
            'release' => ['required', Rule::in(array_keys(Exam::RELEASES))],
            'rows' => ['nullable', 'array', 'max:30'],
            'rows.*.category' => ['nullable', 'exists:question_categories,id'],
            'rows.*.difficulty' => ['nullable', Rule::in(array_keys(Question::DIFFICULTIES))],
            'rows.*.count' => ['nullable', 'integer', 'min:0', 'max:300'],
        ], ['org_unit_id.in' => 'Choose a place where you can run examinations.', 'closes_at.after' => 'The closing time must be after the opening time.']);

        if (! empty($data['course_id'])) {
            $course = Course::find($data['course_id']);
            abort_unless(in_array($course->org_unit_id, $unitIds), 403);
            $data['org_unit_id'] = $course->org_unit_id;
        }
        $exam->fill(collect($data)->except('rows')->all() + [
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'shuffle_options' => $request->boolean('shuffle_options'),
            'show_review' => $request->boolean('show_review'),
            'awards_certificate' => $request->boolean('awards_certificate') && $data['mode'] === 'certification',
            'requires_course_completion' => $request->boolean('requires_course_completion') && ! empty($data['course_id']),
        ]);
    }

    protected function saveBlueprint(Exam $exam, Request $request): void
    {
        $rows = collect($request->input('rows', []))->filter(fn ($r) => (int) ($r['count'] ?? 0) > 0);
        $exam->blueprint()->delete();
        foreach ($rows as $r) {
            $exam->blueprint()->create(['question_category_id' => $r['category'] ?: null, 'difficulty' => $r['difficulty'] ?: null, 'count' => (int) $r['count']]);
        }
        if ($rows->isNotEmpty()) {
            $exam->forceFill(['question_count' => $rows->sum('count')])->save();
        }
    }

    protected function paths(Request $request): array
    {
        $paths = $this->access->paths($request->user(), 'exams.manage');
        abort_if(empty($paths), 403);

        return $paths;
    }

    protected function authorise(Request $request, Exam $exam): void
    {
        abort_unless($exam->canBeManagedBy($request->user()), 403);
    }

    /** Exams run by a unit inside the viewer's area. */
    protected function withinPaths(Builder $q, array $paths): void
    {
        $q->whereHas('orgUnit', function ($q) use ($paths) {
            $q->where(function ($q) use ($paths) {
                foreach ($paths as $path) {
                    $q->orWhere('path', 'like', $path.'%');
                }
            });
        });
    }
}
