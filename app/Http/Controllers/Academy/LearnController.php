<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\CourseResource;
use App\Models\CourseSession;
use App\Models\Enrolment;
use App\Models\Lesson;
use App\Models\OrgUnit;
use App\Models\Prompt;
use App\Models\PromptResponse;
use App\Services\Academy;
use App\Services\Access;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** The GODRAM Virtual Academy as members see it. */
class LearnController extends Controller
{
    public function __construct(protected Academy $academy, protected Access $access) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $level = $request->input('level');
        $levels = ['national' => OrgUnit::NATIONAL, 'regional' => OrgUnit::REGION, 'district' => OrgUnit::DISTRICT];
        $term = trim((string) $request->input('q'));

        $courses = Course::visibleTo($user)->with(['orgUnit', 'facilitators.member'])->withCount('lessons')
            ->when(isset($levels[$level]), fn ($q) => $q->whereHas('orgUnit', fn ($q) => $q->where('type', $levels[$level])))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('summary', 'like', "%{$term}%")))
            ->latest('published_at')->get();

        $visibleIds = Course::visibleTo($user)->pluck('id');
        $live = CourseSession::whereIn('course_id', $visibleIds)->whereNotNull('live_at')
            ->where('live_at', '<=', now()->addDays(45))
            ->where(fn ($q) => $q->where('live_ends_at', '>=', now())->orWhere(fn ($q) => $q->whereNull('live_ends_at')->where('live_at', '>=', now()->subHours(CourseSession::LIVE_HOURS))))
            ->with('course.orgUnit')->orderBy('live_at')->limit(6)->get();

        $mine = collect();
        if ($user?->member_id) {
            $mine = Enrolment::where('member_id', $user->member_id)->where('status', '!=', 'withdrawn')
                ->with(['course.sessions.lessons', 'progress', 'lastLesson'])->latest('updated_at')->get()
                ->map(fn ($e) => (object) ['enrolment' => $e, 'course' => $e->course, 'progress' => $this->academy->progress($e, $e->course)]);
        }

        return view('academy.index', [
            'courses' => $courses,
            'live' => $live,
            'mine' => $mine,
            'level' => isset($levels[$level]) ? $level : null,
            'term' => $term,
            'canManage' => $this->access->can($user, 'training.manage') || ($user?->member_id && \App\Models\CourseFacilitator::where('member_id', $user->member_id)->exists()),
            'canOverview' => $this->access->can($user, 'training.view'),
        ]);
    }

    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($course->isVisibleTo($user), 404);
        $course->load(['orgUnit', 'targets', 'facilitators.member', 'sessions.lessons', 'sessions.resources', 'resources' => fn ($q) => $q->whereNull('lesson_id')->whereNull('course_session_id'), 'assignments']);
        $enrolment = $course->enrolmentFor($user);
        $enrolment?->load('progress');

        return view('academy.show', [
            'course' => $course,
            'enrolment' => $enrolment?->status === 'withdrawn' ? null : $enrolment,
            'progress' => $this->academy->progress($enrolment, $course),
            'canTeach' => $course->canTeach($user),
            'submissions' => $enrolment ? AssignmentSubmission::whereIn('assignment_id', $course->assignments->pluck('id'))->where('member_id', $user->member_id)->get()->keyBy('assignment_id') : collect(),
            'questions' => $course->questions()->where('is_hidden', false)
                ->where(fn ($q) => $q->whereNotNull('answer')->orWhere('user_id', $user?->id ?? 0))
                ->with('user.member', 'lesson')->orderByDesc('is_featured')->latest()->limit(20)->get(),
            'enrolledCount' => $course->enrolments()->where('status', '!=', 'withdrawn')->count(),
        ]);
    }

    public function enrol(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($user->member && $course->isVisibleTo($user), 404);
        if (! $course->acceptsEnrolment()) {
            return back()->withErrors(['enrol' => 'Enrolment for this training has closed.']);
        }
        $this->academy->enrol($course, $user->member);
        $first = $course->orderedLessons()->first();

        return ($first ? redirect()->route('academy.lesson', [$course, $first]) : back())
            ->with('status', 'You are enrolled. Welcome to '.$course->title.'.');
    }

    public function withdraw(Request $request, Course $course)
    {
        $enrolment = $course->enrolmentFor($request->user());
        abort_unless($enrolment, 404);
        $this->academy->withdraw($enrolment);

        return redirect()->route('academy.show', $course)->with('status', 'You have left this training. Your progress is kept if you return.');
    }

    public function lesson(Request $request, Course $course, Lesson $lesson)
    {
        $user = $request->user();
        abort_unless($course->isVisibleTo($user), 404);
        $course->load(['sessions.lessons', 'facilitators.member', 'orgUnit']);
        $enrolment = $course->enrolmentFor($user);
        $enrolled = $enrolment && $enrolment->status !== 'withdrawn';
        $canTeach = $course->canTeach($user);

        if (! $lesson->is_preview && ! $enrolled && ! $canTeach) {
            return redirect()->route('academy.show', $course)->with('status', $user ? 'Enrol to open the lessons.' : 'Sign in and enrol to open the lessons.');
        }
        if ($enrolled) {
            $this->academy->touch($enrolment, $lesson);
            $enrolment->load('progress');
        }
        $lesson->load(['session', 'resources', 'prompts']);

        return view('academy.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'enrolment' => $enrolled ? $enrolment : null,
            'progress' => $this->academy->progress($enrolled ? $enrolment : null, $course),
            'previous' => $this->academy->previousLesson($course, $lesson),
            'next' => $this->academy->nextLesson($course, $lesson),
            'responses' => $user ? PromptResponse::whereIn('prompt_id', $lesson->prompts->pluck('id'))->where('user_id', $user->id)->get()->keyBy('prompt_id') : collect(),
            'questions' => CourseQuestion::where('lesson_id', $lesson->id)->where('is_hidden', false)
                ->where(fn ($q) => $q->whereNotNull('answer')->orWhere('user_id', $user?->id ?? 0))->with('user.member')->latest()->get(),
            'canTeach' => $canTeach,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson)
    {
        $enrolment = $course->enrolmentFor($request->user());
        abort_unless($enrolment && $enrolment->status !== 'withdrawn', 403);
        $next = $this->academy->complete($enrolment, $lesson);

        if ($next) {
            return redirect()->route('academy.lesson', [$course, $next]);
        }

        return redirect()->route('academy.show', $course)->with('status', $enrolment->fresh()->status === 'completed'
            ? 'Well done. You have completed every lesson of '.$course->title.'.'
            : 'Lesson completed.');
    }

    public function audio(Request $request, Course $course, Lesson $lesson)
    {
        $this->authoriseContent($request, $course, $lesson->is_preview);
        abort_unless($lesson->audio_path && Storage::disk('local')->exists($lesson->audio_path), 404);

        return response()->file(Storage::disk('local')->path($lesson->audio_path), ['Cache-Control' => 'private, max-age=604800']);
    }

    public function resource(Request $request, Course $course, CourseResource $resource)
    {
        $this->authoriseContent($request, $course);
        if ($resource->isLink()) {
            return redirect()->away($resource->url);
        }
        abort_unless($resource->path && Storage::disk('local')->exists($resource->path), 404);

        return response()->download(Storage::disk('local')->path($resource->path), $resource->file_name ?? basename($resource->path));
    }

    public function respond(Request $request, Course $course, Prompt $prompt)
    {
        $this->authoriseContent($request, $course, (bool) $prompt->lesson_id && Lesson::whereKey($prompt->lesson_id)->value('is_preview'));
        $data = $request->validate(['response' => ['required', 'string', 'max:1000']], ['response.required' => 'Write or choose an answer first.']);
        $response = $this->academy->respond($prompt, $request->user(), $data['response']);

        if ($request->expectsJson()) {
            return response()->json([
                'correct' => $response->is_correct,
                'answer' => $prompt->isMarked() ? explode('|', $prompt->answer)[0] : null,
                'explanation' => $prompt->explanation,
                'tally' => $prompt->type === 'poll' ? $prompt->tally() : null,
            ]);
        }

        return back()->with('status', match ($response->is_correct) {
            true => 'Correct.',
            false => 'Not quite. The answer is: '.explode('|', $prompt->answer)[0].'.',
            null => 'Thank you. Your response has been recorded.',
        });
    }

    public function room(Request $request, Course $course, CourseSession $session)
    {
        $user = $request->user();
        abort_unless($course->isVisibleTo($user), 404);
        $enrolment = $course->enrolmentFor($user);
        $canTeach = $course->canTeach($user);
        abort_unless(($enrolment && $enrolment->status !== 'withdrawn') || $canTeach, 403, 'Enrol in the training to join its live classes.');
        if ($session->isLive() && $enrolment && $user->member) {
            $this->academy->markJoined($session, $user->member);
        }
        $session->load(['lessons', 'resources', 'prompts']);

        return view('academy.room', [
            'course' => $course->load('facilitators.member', 'orgUnit'),
            'session' => $session,
            'canTeach' => $canTeach,
            'responses' => PromptResponse::whereIn('prompt_id', $session->prompts->pluck('id'))->where('user_id', $user->id)->get()->keyBy('prompt_id'),
            'attendance' => $enrolment ? $session->attendance()->where('member_id', $user->member_id)->first() : null,
        ]);
    }

    /** Polled every few seconds by the live room: open prompts, answered questions, how many have joined. */
    public function feed(Request $request, Course $course, CourseSession $session)
    {
        $user = $request->user();
        abort_unless($course->enrolmentFor($user) || $course->canTeach($user), 403);
        $canTeach = $course->canTeach($user);
        $mine = PromptResponse::whereIn('prompt_id', $session->prompts()->pluck('id'))->where('user_id', $user->id)->pluck('response', 'prompt_id');

        return response()->json([
            'live' => $session->isLive(),
            'joined' => $session->attendance()->whereIn('status', ['joined', 'attended'])->count(),
            'prompts' => $session->prompts()->where(fn ($q) => $q->where('is_live', true)->orWhere('show_results', true))->get()->map(fn (Prompt $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'typeLabel' => $p->typeLabel(),
                'question' => $p->question,
                'choices' => $p->choices(),
                'open' => $p->is_live,
                'mine' => $mine[$p->id] ?? null,
                'responses' => $p->responses()->count(),
                'tally' => ($p->show_results || $canTeach) && $p->hasChoices() ? $p->tally() : null,
                'answer' => $p->show_results && $p->isMarked() ? explode('|', $p->answer)[0] : null,
                'url' => route('academy.respond', [$course, $p]),
            ])->values(),
            'questions' => CourseQuestion::where('course_session_id', $session->id)->where('is_hidden', false)
                ->when(! $canTeach, fn ($q) => $q->where(fn ($q) => $q->whereNotNull('answer')->orWhere('is_featured', true)->orWhere('user_id', $user->id)))
                ->with('user.member')->orderByDesc('is_featured')->latest()->limit(40)->get()
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'who' => $q->user?->member?->first_name ?? 'A participant',
                    'body' => $q->body,
                    'answer' => $q->answer,
                    'featured' => $q->is_featured,
                    'mine' => $q->user_id === $user->id,
                    'at' => $q->created_at->format('g:ia'),
                ])->values(),
        ]);
    }

    public function ask(Request $request, Course $course)
    {
        $user = $request->user();
        abort_unless($course->enrolmentFor($user) || $course->canTeach($user), 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'lesson_id' => ['nullable', 'integer'],
            'course_session_id' => ['nullable', 'integer'],
        ], ['body.required' => 'Type your question first.']);
        $question = $course->questions()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
            'lesson_id' => $course->lessons()->whereKey($data['lesson_id'] ?? 0)->value('id'),
            'course_session_id' => $course->sessions()->whereKey($data['course_session_id'] ?? 0)->value('id'),
        ]);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'id' => $question->id])
            : back()->with('status', 'Your question has been sent to the facilitators.');
    }

    public function assignment(Request $request, Course $course, Assignment $assignment)
    {
        $user = $request->user();
        $enrolment = $course->enrolmentFor($user);
        abort_unless(($enrolment && $enrolment->status !== 'withdrawn') || $course->canTeach($user), 403);

        return view('academy.assignment', [
            'course' => $course,
            'assignment' => $assignment,
            'submission' => $user->member_id ? $assignment->submissions()->where('member_id', $user->member_id)->first() : null,
        ]);
    }

    public function submit(Request $request, Course $course, Assignment $assignment, ImageStore $files)
    {
        $user = $request->user();
        $enrolment = $course->enrolmentFor($user);
        abort_unless($enrolment && $enrolment->status !== 'withdrawn', 403);
        $existing = $assignment->submissions()->where('member_id', $user->member_id)->first();
        abort_if($existing?->status === 'accepted', 422, 'This assignment has already been accepted.');

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'link' => ['nullable', 'url', 'max:255'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,ppt,pptx,txt,rtf,jpg,jpeg,png,webp,mp3,m4a,ogg,wav,aac'],
        ]);
        if (! filled($data['body'] ?? null) && ! filled($data['link'] ?? null) && ! $request->hasFile('file') && ! $existing?->file_path) {
            throw ValidationException::withMessages(['body' => 'Add your work: write it, attach a file or paste a link.']);
        }

        $values = [
            'body' => $assignment->accepts('text') ? ($data['body'] ?? null) : null,
            'link' => $assignment->accepts('link') ? ($data['link'] ?? null) : null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
        if ($request->hasFile('file') && $assignment->accepts('file')) {
            $values['file_path'] = $files->storeDocument($request->file('file'), 'academy/submissions');
            $values['file_name'] = $request->file('file')->getClientOriginalName();
        }
        AssignmentSubmission::updateOrCreate(['assignment_id' => $assignment->id, 'member_id' => $user->member_id], $values);

        return redirect()->route('academy.assignment', [$course, $assignment])->with('status', 'Submitted. Your facilitator will read it and reply here.');
    }

    public function submissionFile(Request $request, AssignmentSubmission $submission)
    {
        $user = $request->user();
        $course = $submission->assignment->course;
        abort_unless($submission->member_id === $user->member_id || $course->canTeach($user), 403);
        abort_unless($submission->file_path && Storage::disk('local')->exists($submission->file_path), 404);

        return response()->download(Storage::disk('local')->path($submission->file_path), $submission->file_name);
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        abort_unless($user->member_id, 404);
        $enrolments = Enrolment::where('member_id', $user->member_id)->where('status', '!=', 'withdrawn')
            ->with(['course.sessions.lessons', 'course.assignments', 'course.orgUnit', 'progress', 'lastLesson'])->latest('updated_at')->get();
        $submissions = AssignmentSubmission::where('member_id', $user->member_id)->get()->keyBy('assignment_id');

        return view('academy.mine', [
            'items' => $enrolments->map(fn ($e) => (object) [
                'enrolment' => $e,
                'course' => $e->course,
                'progress' => $this->academy->progress($e, $e->course),
                'assignments' => $e->course->assignments->map(fn ($a) => (object) ['assignment' => $a, 'submission' => $submissions[$a->id] ?? null]),
                'nextLive' => $e->course->nextLiveSession(),
            ]),
        ]);
    }

    protected function authoriseContent(Request $request, Course $course, bool $preview = false): void
    {
        $user = $request->user();
        abort_unless($course->isVisibleTo($user), 404);
        $enrolment = $course->enrolmentFor($user);
        abort_unless($preview || ($enrolment && $enrolment->status !== 'withdrawn') || $course->canTeach($user), 403);
    }
}
