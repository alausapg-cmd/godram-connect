<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSession;
use App\Models\Enrolment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Member;
use App\Models\Prompt;
use App\Models\PromptResponse;
use App\Models\SessionAttendance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Enrolment, lesson progress, call-and-response and attendance for the GODRAM Virtual Academy. */
class Academy
{
    public function __construct(protected AuditLogger $audit) {}

    public function enrol(Course $course, Member $member): Enrolment
    {
        $enrolment = Enrolment::firstOrNew(['course_id' => $course->id, 'member_id' => $member->id]);
        if (! $enrolment->exists || $enrolment->status === 'withdrawn') {
            $enrolment->status = 'active';
            $enrolment->save();
            $this->audit->log('academy.enrolled', $course, $member->full_name.' enrolled in '.$course->title, [], $member->assembly());
        }

        return $enrolment;
    }

    public function withdraw(Enrolment $enrolment): void
    {
        $enrolment->update(['status' => 'withdrawn']);
    }

    /** Marks a lesson done and, when every lesson is done, the course. Returns the next lesson, if any. */
    public function complete(Enrolment $enrolment, Lesson $lesson): ?Lesson
    {
        DB::transaction(function () use ($enrolment, $lesson) {
            LessonProgress::firstOrCreate(
                ['enrolment_id' => $enrolment->id, 'lesson_id' => $lesson->id],
                ['completed_at' => now()],
            );
            $enrolment->last_lesson_id = $lesson->id;
            $summary = $this->progress($enrolment->fresh(), $lesson->course);
            if ($summary['total'] > 0 && $summary['done'] >= $summary['total'] && $enrolment->status !== 'completed') {
                $enrolment->status = 'completed';
                $enrolment->completed_at = now();
                $this->audit->log('academy.lessons_completed', $lesson->course, $enrolment->member->full_name.' completed every lesson of '.$lesson->course->title);
            }
            $enrolment->save();
        });

        return $this->nextLesson($lesson->course, $lesson);
    }

    public function touch(Enrolment $enrolment, Lesson $lesson): void
    {
        if ($enrolment->last_lesson_id !== $lesson->id) {
            $enrolment->forceFill(['last_lesson_id' => $lesson->id])->save();
        }
    }

    /** "5 / 8 lessons completed", plus where to carry on. */
    public function progress(?Enrolment $enrolment, Course $course): array
    {
        $lessons = $course->orderedLessons();
        $done = $enrolment ? array_intersect($enrolment->completedLessonIds(), $lessons->pluck('id')->all()) : [];
        $next = $lessons->first(fn (Lesson $l) => ! in_array($l->id, $done));

        return [
            'done' => count($done),
            'total' => $lessons->count(),
            'percent' => $lessons->count() ? (int) round(count($done) / $lessons->count() * 100) : 0,
            'doneIds' => array_values($done),
            'next' => $next,
        ];
    }

    public function nextLesson(Course $course, Lesson $lesson): ?Lesson
    {
        $lessons = $course->orderedLessons();
        $i = $lessons->search(fn ($l) => $l->id === $lesson->id);

        return $i === false ? null : $lessons->get($i + 1);
    }

    public function previousLesson(Course $course, Lesson $lesson): ?Lesson
    {
        $lessons = $course->orderedLessons();
        $i = $lessons->search(fn ($l) => $l->id === $lesson->id);

        return $i ? $lessons->get($i - 1) : null;
    }

    /** A participant answers a call-and-response prompt. Answers can be changed until results are shown. */
    public function respond(Prompt $prompt, User $user, string $response): PromptResponse
    {
        $response = trim($response);
        if ($prompt->hasChoices() && ! in_array($response, $prompt->choices(), true)) {
            abort(422, 'Choose one of the options.');
        }

        return PromptResponse::updateOrCreate(
            ['prompt_id' => $prompt->id, 'user_id' => $user->id],
            ['response' => mb_substr($response, 0, 1000), 'is_correct' => $prompt->check($response)],
        );
    }

    /** The member says they are in the live class. A facilitator can confirm it later. */
    public function markJoined(CourseSession $session, Member $member): void
    {
        $row = SessionAttendance::firstOrNew(['course_session_id' => $session->id, 'member_id' => $member->id]);
        if (! $row->exists) {
            $row->fill(['status' => 'joined', 'joined_at' => now()])->save();
        }
    }

    public function confirmAttendance(CourseSession $session, Member $member, string $status, User $by): void
    {
        SessionAttendance::updateOrCreate(
            ['course_session_id' => $session->id, 'member_id' => $member->id],
            ['status' => $status, 'confirmed_by' => $by->id],
        );
    }

    /** Enrolment, progress and attendance for members in the given branch paths. */
    public function overview(array $paths, ?Collection $courses = null): Collection
    {
        $courses ??= Course::published()->with(['orgUnit', 'sessions.lessons'])->latest('published_at')->get();

        return $courses->map(function (Course $course) use ($paths) {
            $enrolments = Enrolment::where('course_id', $course->id)->where('status', '!=', 'withdrawn')
                ->whereHas('member', fn ($q) => $q->placedWithinPaths($paths))
                ->with('progress', 'member.currentPlacement.orgUnit')->get();
            $total = $course->orderedLessons()->count();
            $liveSessions = $course->sessions->filter->isLiveSession()->filter->isOver();
            $attended = $liveSessions->isEmpty() ? null : SessionAttendance::whereIn('course_session_id', $liveSessions->pluck('id'))
                ->whereIn('member_id', $enrolments->pluck('member_id'))->whereIn('status', ['joined', 'attended'])->count();
            $assignmentIds = $course->assignments()->pluck('id');
            $outstanding = $assignmentIds->isEmpty() ? 0 : $enrolments->count() * $assignmentIds->count()
                - \App\Models\AssignmentSubmission::whereIn('assignment_id', $assignmentIds)->whereIn('member_id', $enrolments->pluck('member_id'))->count();

            return (object) [
                'course' => $course,
                'enrolled' => $enrolments->count(),
                'completed' => $enrolments->where('status', 'completed')->count(),
                'started' => $enrolments->filter(fn ($e) => $e->progress->isNotEmpty())->count(),
                'lessons' => $total,
                'attendance' => $attended === null ? null : [$attended, $liveSessions->count() * max(1, $enrolments->count())],
                'outstanding' => max(0, $outstanding),
                'enrolments' => $enrolments,
            ];
        });
    }
}
