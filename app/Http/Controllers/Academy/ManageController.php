<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseFacilitator;
use App\Models\CourseQuestion;
use App\Models\CourseResource;
use App\Models\CourseSession;
use App\Models\Event;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\Prompt;
use App\Models\Role;
use App\Models\SessionAttendance;
use App\Models\Video;
use App\Services\Academy;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Training administration and the facilitator's tools. */
class ManageController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit, protected Academy $academy) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $paths = $this->access->paths($user, 'training.manage');
        $courses = Course::query()
            ->where(function ($q) use ($paths, $user) {
                $q->whereHas('facilitators', fn ($q) => $q->where('member_id', $user->member_id ?? 0));
                if ($paths) {
                    $q->orWhereHas('orgUnit', function ($q) use ($paths) {
                        $q->where(function ($q) use ($paths) {
                            foreach ($paths as $p) {
                                $q->orWhere('path', 'like', $p.'%');
                            }
                        });
                    });
                }
            })
            ->with('orgUnit')->withCount(['lessons', 'enrolments' => fn ($q) => $q->where('status', '!=', 'withdrawn')])
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'published' THEN 1 ELSE 2 END")->latest('updated_at')->get();
        abort_if($courses->isEmpty() && ! $paths, 403);

        $ids = $courses->pluck('id');

        return view('academy.manage.index', [
            'courses' => $courses,
            'canCreate' => (bool) $paths,
            'toReview' => AssignmentSubmission::where('status', 'submitted')->whereHas('assignment', fn ($q) => $q->whereIn('course_id', $ids))->count(),
            'toAnswer' => CourseQuestion::whereIn('course_id', $ids)->whereNull('answer')->where('is_hidden', false)->count(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'training.manage'), 403);

        return $this->form($request, new Course(['kind' => 'blended', 'is_public' => true]));
    }

    public function store(Request $request, ImageStore $images)
    {
        abort_unless($this->access->can($request->user(), 'training.manage'), 403);
        $data = $this->validated($request);
        $course = DB::transaction(function () use ($request, $data) {
            $course = Course::create($data['course'] + ['created_by' => $request->user()->id, 'status' => 'draft']);
            $this->syncTargets($course, $data);
            $this->syncFacilitators($course, $data['facilitators']);

            return $course;
        });
        $this->saveCover($request, $course, $images);
        $this->audit->log('academy.course_created', $course, 'Training created: '.$course->title, [], $course->orgUnit);

        return redirect()->route('academy.manage.build', $course)->with('status', 'Training created as a draft. Add sessions and lessons, then open it to members.');
    }

    public function edit(Request $request, Course $course)
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);

        return $this->form($request, $course->load('targets', 'facilitators.member'));
    }

    public function update(Request $request, Course $course, ImageStore $images)
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($course, $data) {
            $course->update($data['course']);
            $this->syncTargets($course, $data);
            $this->syncFacilitators($course, $data['facilitators']);
        });
        $this->saveCover($request, $course, $images);
        $this->audit->log('academy.course_updated', $course, 'Training updated: '.$course->title, [], $course->orgUnit);

        return redirect()->route('academy.manage.build', $course)->with('status', 'Saved.');
    }

    public function status(Request $request, Course $course)
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'archived'])]]);
        if ($data['status'] === 'published' && $course->lessons()->doesntExist() && $course->sessions()->whereNotNull('live_at')->doesntExist()) {
            return back()->withErrors(['status' => 'Add at least one lesson or live session before opening the training.']);
        }
        $course->forceFill(['status' => $data['status'], 'published_at' => $data['status'] === 'published' ? ($course->published_at ?? now()) : $course->published_at])->save();
        $this->audit->log('academy.course_'.$data['status'], $course, ucfirst($data['status']).': '.$course->title, [], $course->orgUnit);

        return back()->with('status', match ($data['status']) {
            'published' => 'The training is open. Members it is aimed at can now see it and enrol.',
            'draft' => 'Moved back to draft. Members can no longer see it.',
            'archived' => 'Archived. It stays in the training archive.',
        });
    }

    public function build(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $course->load(['orgUnit', 'targets', 'facilitators.member', 'sessions.lessons', 'sessions.prompts', 'resources', 'assignments']);

        return view('academy.manage.build', [
            'course' => $course,
            'canManage' => $course->canBeManagedBy($request->user()),
            'enrolled' => $course->enrolments()->where('status', '!=', 'withdrawn')->count(),
            'toReview' => AssignmentSubmission::where('status', 'submitted')->whereIn('assignment_id', $course->assignments->pluck('id'))->count(),
            'toAnswer' => $course->questions()->whereNull('answer')->where('is_hidden', false)->count(),
        ]);
    }

    // Sessions ---------------------------------------------------------------

    public function storeSession(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $course->sessions()->create($this->sessionData($request) + ['sort' => (int) $course->sessions()->max('sort') + 1]);

        return redirect()->route('academy.manage.build', $course)->with('status', 'Session added.');
    }

    public function updateSession(Request $request, Course $course, CourseSession $session)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $session->update($this->sessionData($request));

        return redirect()->route('academy.manage.build', $course)->with('status', 'Session saved.');
    }

    public function destroySession(Request $request, Course $course, CourseSession $session)
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);
        abort_if($session->lessons()->exists(), 422, 'Move or delete the lessons in this session first.');
        $session->delete();

        return back()->with('status', 'Session removed.');
    }

    public function moveSession(Request $request, Course $course, CourseSession $session)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $this->move($course->sessions()->get(), $session, $request->input('direction'));

        return back();
    }

    /** The facilitator opens or closes the live room's question and response tools. */
    public function roomToggle(Request $request, Course $course, CourseSession $session)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $session->forceFill(['room_open' => ! $session->room_open])->save();

        return back();
    }

    // Lessons ----------------------------------------------------------------

    public function createLesson(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $session = $course->sessions()->findOrFail($request->integer('session'));

        return view('academy.manage.lesson', ['course' => $course->load('sessions'), 'lesson' => new Lesson(['kind' => 'text', 'course_session_id' => $session->id])]);
    }

    public function storeLesson(Request $request, Course $course, ImageStore $files)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $data = $this->lessonData($request, $course);
        $lesson = $course->lessons()->create($data + ['sort' => (int) Lesson::where('course_session_id', $data['course_session_id'])->max('sort') + 1]);
        $this->saveLessonFiles($request, $course, $lesson, $files);

        return redirect()->route('academy.manage.lessons.edit', [$course, $lesson])->with('status', 'Lesson saved. Add questions and resources below, or go back to the outline.');
    }

    public function editLesson(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($course->canTeach($request->user()), 403);

        return view('academy.manage.lesson', ['course' => $course->load('sessions'), 'lesson' => $lesson->load('prompts', 'resources')]);
    }

    public function updateLesson(Request $request, Course $course, Lesson $lesson, ImageStore $files)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $lesson->update($this->lessonData($request, $course));
        $this->saveLessonFiles($request, $course, $lesson, $files);

        return redirect()->route('academy.manage.lessons.edit', [$course, $lesson])->with('status', 'Lesson saved.');
    }

    public function destroyLesson(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);
        $lesson->delete();

        return redirect()->route('academy.manage.build', $course)->with('status', 'Lesson deleted.');
    }

    public function moveLesson(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $this->move(Lesson::where('course_session_id', $lesson->course_session_id)->orderBy('sort')->orderBy('id')->get(), $lesson, $request->input('direction'));

        return back();
    }

    // Resources --------------------------------------------------------------

    public function storeResource(Request $request, Course $course, ImageStore $files)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'file' => ['nullable', 'required_without:url', 'file', 'max:'.config('godram.uploads.resource_max_kb', 25600), 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,rtf,jpg,jpeg,png,webp,mp3,m4a,ogg,wav,aac'],
            'url' => ['nullable', 'required_without:file', 'url', 'max:255'],
            'lesson_id' => ['nullable', 'integer'],
            'course_session_id' => ['nullable', 'integer'],
        ], ['file.required_without' => 'Choose a file or paste a link.', 'url.required_without' => 'Choose a file or paste a link.']);

        $values = [
            'lesson_id' => $course->lessons()->whereKey($data['lesson_id'] ?? 0)->value('id'),
            'course_session_id' => $course->sessions()->whereKey($data['course_session_id'] ?? 0)->value('id'),
            'added_by' => $request->user()->id,
            'sort' => (int) $course->resources()->max('sort') + 1,
        ];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $values += [
                'path' => $files->storeDocument($file, 'academy/resources'),
                'file_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'title' => $data['title'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ];
        } else {
            $values += ['url' => $data['url'], 'title' => $data['title'] ?: parse_url($data['url'], PHP_URL_HOST)];
        }
        $course->resources()->create($values);

        return back()->with('status', 'Resource added.');
    }

    public function destroyResource(Request $request, Course $course, CourseResource $resource)
    {
        abort_unless($course->canTeach($request->user()), 403);
        if ($resource->path) {
            Storage::disk('local')->delete($resource->path);
        }
        $resource->delete();

        return back()->with('status', 'Resource removed.');
    }

    // Call-and-response ------------------------------------------------------

    public function storePrompt(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Prompt::TYPES))],
            'question' => ['required', 'string', 'max:500'],
            'options' => ['nullable', 'string', 'max:1000'],
            'answer' => ['nullable', 'string', 'max:300'],
            'explanation' => ['nullable', 'string', 'max:500'],
            'lesson_id' => ['nullable', 'integer'],
            'course_session_id' => ['nullable', 'integer'],
        ]);
        $options = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['options'] ?? '')))));
        if (in_array($data['type'], ['choice', 'poll']) && count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'Give at least two options, one per line.']);
        }
        if ($data['type'] === 'choice' && ! in_array($data['answer'] ?? '', $options, true)) {
            throw ValidationException::withMessages(['answer' => 'Copy the correct option exactly as written above.']);
        }
        if ($data['type'] === 'true_false' && ! in_array($data['answer'] ?? '', ['True', 'False'], true)) {
            throw ValidationException::withMessages(['answer' => 'Choose True or False.']);
        }

        $course->prompts()->create([
            'type' => $data['type'],
            'question' => $data['question'],
            'options' => in_array($data['type'], ['choice', 'poll']) ? $options : null,
            'answer' => in_array($data['type'], ['poll', 'open']) ? null : ($data['answer'] ?? null),
            'explanation' => $data['explanation'] ?? null,
            'lesson_id' => $course->lessons()->whereKey($data['lesson_id'] ?? 0)->value('id'),
            'course_session_id' => $course->sessions()->whereKey($data['course_session_id'] ?? 0)->value('id'),
        ]);

        return back()->with('status', 'Question added.');
    }

    public function updatePrompt(Request $request, Course $course, Prompt $prompt)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $action = $request->validate(['action' => ['required', Rule::in(['live', 'results', 'delete'])]])['action'];
        match ($action) {
            'live' => $prompt->forceFill(['is_live' => ! $prompt->is_live])->save(),
            'results' => $prompt->forceFill(['show_results' => ! $prompt->show_results, 'is_live' => $prompt->show_results ? $prompt->is_live : false])->save(),
            'delete' => $prompt->delete(),
        };

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    // Assignments ------------------------------------------------------------

    public function storeAssignment(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $course->assignments()->create($this->assignmentData($request, $course) + ['sort' => (int) $course->assignments()->max('sort') + 1]);

        return back()->with('status', 'Assignment added.');
    }

    public function updateAssignment(Request $request, Course $course, Assignment $assignment)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $assignment->update($this->assignmentData($request, $course));

        return back()->with('status', 'Assignment saved.');
    }

    public function destroyAssignment(Request $request, Course $course, Assignment $assignment)
    {
        abort_unless($course->canBeManagedBy($request->user()), 403);
        abort_if($assignment->submissions()->exists(), 422, 'Members have already submitted work for this assignment.');
        $assignment->delete();

        return back()->with('status', 'Assignment removed.');
    }

    public function submissions(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $course->load('assignments');

        return view('academy.manage.submissions', [
            'course' => $course,
            'submissions' => AssignmentSubmission::whereIn('assignment_id', $course->assignments->pluck('id'))
                ->with('assignment', 'member.currentPlacement.orgUnit', 'reviewer.member')
                ->orderByRaw("CASE status WHEN 'submitted' THEN 0 ELSE 1 END")->latest('submitted_at')->get(),
        ]);
    }

    public function review(Request $request, Course $course, AssignmentSubmission $submission)
    {
        abort_unless($course->canTeach($request->user()) && $submission->assignment->course_id === $course->id, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'returned'])],
            'feedback' => ['nullable', 'required_if:status,returned', 'string', 'max:5000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:'.($submission->assignment->max_score ?? 1000)],
        ], ['feedback.required_if' => 'Tell the member what to improve.']);
        $submission->forceFill($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
        $this->audit->log('academy.assignment_'.$data['status'], $course, ucfirst($data['status']).': '.$submission->assignment->title.' by '.$submission->member->full_name);

        return back()->with('status', $data['status'] === 'accepted' ? 'Accepted. The member can see your feedback.' : 'Returned to the member with your feedback.');
    }

    // Q&A --------------------------------------------------------------------

    public function questions(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);

        return view('academy.manage.questions', [
            'course' => $course,
            'questions' => $course->questions()->with('user.member', 'lesson', 'session', 'answerer.member')
                ->orderByRaw('CASE WHEN answer IS NULL THEN 0 ELSE 1 END')->latest()->get(),
        ]);
    }

    public function moderate(Request $request, Course $course, CourseQuestion $question)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['answer', 'feature', 'hide'])],
            'answer' => ['nullable', 'required_if:action,answer', 'string', 'max:3000'],
        ]);
        match ($data['action']) {
            'answer' => $question->forceFill(['answer' => $data['answer'], 'answered_by' => $request->user()->id, 'answered_at' => now()])->save(),
            'feature' => $question->forceFill(['is_featured' => ! $question->is_featured])->save(),
            'hide' => $question->forceFill(['is_hidden' => ! $question->is_hidden])->save(),
        };

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', match ($data['action']) {
            'answer' => 'Answer saved. It is kept with the training for others to read.',
            'feature' => $question->is_featured ? 'Featured.' : 'No longer featured.',
            'hide' => $question->is_hidden ? 'Hidden from participants.' : 'Shown again.',
        });
    }

    // Participants and attendance -------------------------------------------

    public function people(Request $request, Course $course)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $course->load('sessions.lessons', 'assignments');
        $enrolments = $course->enrolments()->where('status', '!=', 'withdrawn')
            ->with('member.currentPlacement.orgUnit.parent', 'progress')->get()->sortBy('member.full_name')->values();
        $liveSessions = $course->sessions->filter->isLiveSession()->values();
        $attendance = SessionAttendance::whereIn('course_session_id', $liveSessions->pluck('id'))->get()->groupBy('member_id');
        $submissions = AssignmentSubmission::whereIn('assignment_id', $course->assignments->pluck('id'))->get()->groupBy('member_id');
        $total = $course->orderedLessons()->count();

        if ($request->input('format') === 'csv') {
            $this->audit->log('academy.participants_exported', $course, 'Participants exported: '.$course->title, [], $course->orgUnit);

            return response()->streamDownload(function () use ($enrolments, $liveSessions, $attendance, $submissions, $total) {
                $out = fopen('php://output', 'w');
                fputcsv($out, array_merge(['Member ID', 'Name', 'Assembly', 'District', 'Lessons done', 'Lessons', 'Assignments in', 'Status', 'Enrolled'], $liveSessions->pluck('title')->all()));
                foreach ($enrolments as $e) {
                    $unit = $e->member->currentPlacement?->orgUnit;
                    fputcsv($out, array_merge([
                        $e->member->member_no, $e->member->full_name, $unit?->name, $unit?->parent?->name,
                        $e->progress->count(), $total, ($submissions[$e->member_id] ?? collect())->count(), $e->statusLabel(), $e->created_at->format('Y-m-d'),
                    ], $liveSessions->map(fn ($s) => SessionAttendance::STATUSES[($attendance[$e->member_id] ?? collect())->firstWhere('course_session_id', $s->id)?->status] ?? '')->all()));
                }
                fclose($out);
            }, $course->slug.'-participants.csv', ['Content-Type' => 'text/csv']);
        }

        return view('academy.manage.people', compact('course', 'enrolments', 'liveSessions', 'attendance', 'submissions', 'total'));
    }

    public function attendance(Request $request, Course $course, CourseSession $session)
    {
        abort_unless($course->canTeach($request->user()), 403);
        $data = $request->validate(['status' => ['required', 'array'], 'status.*' => [Rule::in(['attended', 'absent', ''])]]);
        $members = $course->enrolments()->whereIn('member_id', array_keys($data['status']))->pluck('member_id');
        foreach ($members as $memberId) {
            if ($status = $data['status'][$memberId] ?? null) {
                $this->academy->confirmAttendance($session, Member::find($memberId), $status, $request->user());
            }
        }

        return back()->with('status', 'Attendance saved for '.$session->title.'.');
    }

    /** District training dashboard: training reaching members in the coordinator's area. */
    public function overview(Request $request)
    {
        $user = $request->user();
        $paths = $this->access->paths($user, 'training.view');
        abort_unless($paths, 403);
        $units = $this->access->units($user, 'training.view');
        $rows = $this->academy->overview($paths)->filter(fn ($r) => $r->enrolled > 0 || $r->course->isVisibleTo($user));

        return view('academy.overview', [
            'units' => $units,
            'rows' => $rows,
            'upcoming' => CourseSession::whereIn('course_id', $rows->pluck('course.id'))->whereNotNull('live_at')->where('live_at', '>=', now()->subHours(2))
                ->with('course')->orderBy('live_at')->limit(6)->get(),
            'totals' => [
                'enrolled' => $rows->sum('enrolled'),
                'completed' => $rows->sum('completed'),
                'outstanding' => $rows->sum('outstanding'),
                'courses' => $rows->count(),
            ],
        ]);
    }

    // Helpers ----------------------------------------------------------------

    protected function form(Request $request, Course $course)
    {
        $units = $this->access->unitsWithin($request->user(), 'training.manage')->whereIn('type', [OrgUnit::NATIONAL, OrgUnit::REGION, OrgUnit::DISTRICT])->values();

        return view('academy.manage.form', [
            'course' => $course,
            'units' => $units,
            'roles' => Role::whereIn('key', ['assembly_coordinator', 'district_coordinator', 'regional_coordinator', 'facilitator', 'examiner', 'training_administrator'])->orderBy('sort')->get(),
        ]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(Course::KINDS))],
            'org_unit_id' => ['required', 'exists:org_units,id'],
            'summary' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:10000'],
            'outcomes' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'enrol_by' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
            'target_units' => ['array'],
            'target_units.*' => ['integer', 'exists:org_units,id'],
            'target_roles' => ['array'],
            'target_roles.*' => ['string', 'exists:roles,key'],
            'facilitators' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'max:'.config('godram.uploads.image_max_kb')],
        ], ['summary.required' => 'Say in one or two sentences what the training is about.']);

        $unit = OrgUnit::findOrFail($data['org_unit_id']);
        abort_unless($this->access->can($request->user(), 'training.manage', $unit), 403);
        foreach (OrgUnit::whereIn('id', $data['target_units'] ?? [])->get() as $target) {
            if (! $target->isWithin($unit)) {
                throw ValidationException::withMessages(['target_units' => $target->fullName().' is outside '.$unit->fullName().'.']);
            }
        }

        $facilitators = [];
        foreach (array_filter(array_map('trim', preg_split('/\R/', (string) ($data['facilitators'] ?? '')))) as $i => $line) {
            [$id, $title] = array_pad(array_map('trim', explode(',', $line, 2)), 2, null);
            $member = Member::where('member_no', strtoupper($id))->first();
            if (! $member) {
                throw ValidationException::withMessages(['facilitators' => 'No member has the ID '.$id.'. Use the Member ID shown on their profile, like GDM-000012.']);
            }
            $facilitators[$member->id] = ['title' => $title ?: null, 'sort' => $i];
        }

        return [
            'course' => collect($data)->only(['title', 'kind', 'org_unit_id', 'summary', 'description', 'outcomes', 'starts_on', 'ends_on', 'enrol_by'])->all() + ['is_public' => $request->boolean('is_public')],
            'units' => $data['target_units'] ?? [],
            'roles' => $data['target_roles'] ?? [],
            'facilitators' => $facilitators,
        ];
    }

    protected function syncTargets(Course $course, array $data): void
    {
        $course->targets()->delete();
        foreach (array_unique($data['units']) as $id) {
            $course->targets()->create(['audience' => 'org_unit', 'org_unit_id' => $id]);
        }
        foreach (array_unique($data['roles']) as $key) {
            $course->targets()->create(['audience' => 'role', 'role_key' => $key]);
        }
    }

    protected function syncFacilitators(Course $course, array $facilitators): void
    {
        $course->facilitators()->whereNotIn('member_id', array_keys($facilitators))->delete();
        foreach ($facilitators as $memberId => $values) {
            CourseFacilitator::updateOrCreate(['course_id' => $course->id, 'member_id' => $memberId], $values);
        }
    }

    protected function saveCover(Request $request, Course $course, ImageStore $images): void
    {
        if ($request->hasFile('cover')) {
            $course->forceFill(['cover_path' => $images->storeImage($request->file('cover'), 'academy', 1600)])->save();
        }
    }

    protected function sessionData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:300'],
            'live_at' => ['nullable', 'date'],
            'live_ends_at' => ['nullable', 'date', 'after:live_at'],
            'live_url' => ['nullable', 'url', 'max:255'],
            'live_platform' => ['nullable', 'required_with:live_url', Rule::in(array_keys(Event::PLATFORMS))],
            'replay_url' => ['nullable', 'string', 'max:255'],
        ], ['live_platform.required_with' => 'Choose where the class is held.']);
        $replay = null;
        if (filled($data['replay_url'] ?? null) && ! ($replay = Video::youtubeIdFrom($data['replay_url']))) {
            throw ValidationException::withMessages(['replay_url' => 'Paste a YouTube link for the recording.']);
        }
        unset($data['replay_url']);

        return $data + ['replay_youtube_id' => $replay];
    }

    protected function lessonData(Request $request, Course $course): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'course_session_id' => ['required', Rule::exists('course_sessions', 'id')->where('course_id', $course->id)],
            'kind' => ['required', Rule::in(array_keys(Lesson::KINDS))],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:60000'],
            'key_points' => ['nullable', 'string', 'max:2000'],
            'scripture' => ['nullable', 'string', 'max:300'],
            'youtube_url' => ['nullable', 'required_if:kind,video', 'string', 'max:255'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'is_preview' => ['nullable', 'boolean'],
            'audio' => ['nullable', 'file', 'mimes:mp3,m4a,ogg,wav,aac', 'max:30720'],
        ], ['youtube_url.required_if' => 'Paste the YouTube link for this video lesson.']);

        $data['youtube_id'] = null;
        if (filled($data['youtube_url'] ?? null) && ! ($data['youtube_id'] = Video::youtubeIdFrom($data['youtube_url']))) {
            throw ValidationException::withMessages(['youtube_url' => 'That does not look like a YouTube video link.']);
        }

        return collect($data)->except(['youtube_url', 'audio'])->all() + ['is_preview' => $request->boolean('is_preview')];
    }

    protected function saveLessonFiles(Request $request, Course $course, Lesson $lesson, ImageStore $files): void
    {
        if ($request->hasFile('audio')) {
            $lesson->forceFill(['audio_path' => $files->storeDocument($request->file('audio'), 'academy/audio')])->save();
        }
    }

    protected function assignmentData(Request $request, Course $course): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'brief' => ['required', 'string', 'max:10000'],
            'accepts' => ['required', 'array', 'min:1'],
            'accepts.*' => [Rule::in(array_keys(Assignment::ACCEPTS))],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'course_session_id' => ['nullable', Rule::exists('course_sessions', 'id')->where('course_id', $course->id)],
        ], ['accepts.required' => 'Choose at least one way to hand in the work.']);

        return $data;
    }

    protected function move($items, $item, ?string $direction): void
    {
        $items = $items->values();
        $i = $items->search(fn ($x) => $x->id === $item->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= $items->count()) {
            return;
        }
        $swap = $items[$i];
        $items[$i] = $items[$j];
        $items[$j] = $swap;
        foreach ($items as $sort => $x) {
            $x->forceFill(['sort' => $sort])->save();
        }
    }
}
