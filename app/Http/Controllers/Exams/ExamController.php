<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\Cbt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** The candidate's side of the CBT portal. */
class ExamController extends Controller
{
    public function __construct(protected Cbt $cbt) {}

    public function index(Request $request)
    {
        $member = $request->user()->member;
        abort_unless($member, 403);
        $exams = Exam::availableTo($request->user())->with('course', 'orgUnit', 'blueprint')->orderByRaw('closes_at is null')->orderBy('closes_at')->get();
        $attempts = ExamAttempt::where('member_id', $member->id)->with('exam')->orderBy('number')->get()->groupBy('exam_id');

        $items = $exams->map(fn (Exam $exam) => (object) [
            'exam' => $exam,
            'attempts' => $attempts->get($exam->id, collect()),
            'result' => $exam->resultsReleased() ? $exam->resultFor($member, $attempts->get($exam->id, collect())) : null,
            'eligibility' => $exam->eligibility($member),
        ]);
        // Past exams the member sat that are no longer listed (archived, or the course ended).
        $past = Exam::whereIn('id', $attempts->keys())->whereNotIn('id', $exams->pluck('id'))->get();

        return view('exams.index', ['items' => $items, 'past' => $past, 'attempts' => $attempts, 'member' => $member]);
    }

    public function show(Request $request, Exam $exam)
    {
        $member = $request->user()->member;
        abort_unless($member && ($exam->status === 'published' || $exam->canBeManagedBy($request->user())), 404);
        $attempts = $exam->attemptsFor($member);
        abort_unless($attempts->isNotEmpty() || Exam::availableTo($request->user())->whereKey($exam->id)->exists() || $exam->canBeManagedBy($request->user()), 404);

        return view('exams.show', [
            'exam' => $exam->load('course', 'orgUnit', 'blueprint'),
            'attempts' => $attempts,
            'current' => $attempts->firstWhere('status', 'in_progress'),
            'eligibility' => $exam->eligibility($member),
            'result' => $exam->resultsReleased() ? $exam->resultFor($member, $attempts) : null,
            'left' => $exam->attemptsLeft($member),
        ]);
    }

    public function start(Request $request, Exam $exam)
    {
        $member = $request->user()->member;
        abort_unless($member, 403);
        try {
            $attempt = $this->cbt->start($exam, $member, $request->ip(), $request->userAgent());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('exams.sit', $attempt);
    }

    public function sit(Request $request, ExamAttempt $attempt)
    {
        $this->own($request, $attempt);
        if (! $attempt->acceptsAnswers()) {
            $this->cbt->submit($attempt, true);

            return redirect()->route('exams.result', $attempt);
        }

        return view('exams.sit', [
            'attempt' => $attempt->load('exam.course'),
            'questions' => $attempt->questions->map->forCandidate()->values(),
        ]);
    }

    /** Server time and deadline, so the countdown follows the server and not the phone's clock. */
    public function state(Request $request, ExamAttempt $attempt)
    {
        $this->own($request, $attempt);
        if ($attempt->status === 'in_progress' && ! $attempt->acceptsAnswers()) {
            $attempt = $this->cbt->submit($attempt, true);
        }

        return response()->json([
            'status' => $attempt->status,
            'now' => now()->getTimestampMs(),
            'deadline' => $attempt->deadline_at->getTimestampMs(),
            'seconds_left' => $attempt->secondsLeft(),
            'result_url' => $attempt->isFinished() ? route('exams.result', $attempt) : null,
        ]);
    }

    public function save(Request $request, ExamAttempt $attempt)
    {
        $this->own($request, $attempt);
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:1'],
            'revision' => ['required', 'integer', 'min:1'],
            'response' => ['nullable'],
            'flagged' => ['nullable', 'boolean'],
            'seconds' => ['nullable', 'integer', 'min:0'],
        ]);
        try {
            $result = $this->cbt->save($attempt, $data['position'], $data['response'] ?? null, $data['revision'], array_key_exists('flagged', $data) ? (bool) $data['flagged'] : null, (int) ($data['seconds'] ?? 0));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'reason' => 'invalid', 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json($result + [
            'now' => now()->getTimestampMs(),
            'deadline' => $attempt->fresh()->deadline_at->getTimestampMs(),
            'result_url' => ($result['ok'] ?? false) ? null : route('exams.result', $attempt),
        ], ($result['ok'] ?? false) ? 200 : 409);
    }

    public function signal(Request $request, ExamAttempt $attempt)
    {
        $this->own($request, $attempt);
        $data = $request->validate(['type' => ['required', 'string', 'max:20'], 'position' => ['nullable', 'integer'], 'seconds' => ['nullable', 'integer', 'min:0']]);
        $this->cbt->signal($attempt, $data['type'], array_filter(['position' => $data['position'] ?? null, 'seconds' => $data['seconds'] ?? null], fn ($v) => $v !== null));

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, ExamAttempt $attempt)
    {
        $this->own($request, $attempt);
        $auto = $request->boolean('auto') && now()->gte($attempt->deadline_at->copy()->subSeconds(5));
        $attempt = $this->cbt->submit($attempt, $auto);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'result_url' => route('exams.result', $attempt)])
            : redirect()->route('exams.result', $attempt);
    }

    public function result(Request $request, ExamAttempt $attempt)
    {
        $this->own($request, $attempt, allowManagers: true);
        if ($attempt->status === 'in_progress') {
            if ($attempt->acceptsAnswers()) {
                return redirect()->route('exams.sit', $attempt);
            }
            $attempt = $this->cbt->submit($attempt, true);
        }
        $exam = $attempt->exam;
        $member = $attempt->member;
        $released = $exam->resultsReleased();

        return view('exams.result', [
            'attempt' => $attempt->load('questions'),
            'exam' => $exam->load('course'),
            'released' => $released,
            'overall' => $released ? $exam->resultFor($member) : null,
            'review' => $released && ($exam->show_review || $exam->isPractice()),
            'certificate' => $released ? $member->certificates()->whereIn('exam_attempt_id', $exam->attempts()->pluck('id'))->first() : null,
            'left' => $exam->attemptsLeft($member),
            'eligibility' => $exam->eligibility($member),
        ]);
    }

    /** Pictures and recordings in questions, only for the candidate whose paper they are on. */
    public function media(Request $request, ExamAttempt $attempt, int $position)
    {
        $this->own($request, $attempt, allowManagers: true);
        $item = $attempt->questions()->where('position', $position)->firstOrFail();
        $path = $item->snapshot['media_path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }

    protected function own(Request $request, ExamAttempt $attempt, bool $allowManagers = false): void
    {
        $mine = $request->user()->member_id && (int) $attempt->member_id === (int) $request->user()->member_id;
        abort_unless($mine || ($allowManagers && $attempt->exam->canBeManagedBy($request->user())), 404);
    }
}
