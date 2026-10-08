<?php

namespace Tests\Feature;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\CourseSession;
use App\Models\Enrolment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class AcademyTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    protected function member(string $first = 'Ngozi', $unit = null): User
    {
        return $this->person($unit ?? $this->agege, $first, 'Learner', password: 'secret-pass-1')->user;
    }

    /** A Lagos District course with two sessions and three lessons. */
    protected function course(array $attributes = []): Course
    {
        $course = Course::create($attributes + [
            'title' => 'Acting basics', 'kind' => 'blended', 'org_unit_id' => $this->lagos->id,
            'summary' => 'Voice, movement and character.', 'status' => 'published', 'published_at' => now(), 'is_public' => false,
        ]);
        $one = $course->sessions()->create(['title' => 'Voice', 'sort' => 0]);
        $two = $course->sessions()->create(['title' => 'Character', 'sort' => 1, 'live_at' => now()->subMinutes(10), 'live_url' => 'https://meet.google.com/abc', 'live_platform' => 'meet']);
        $course->lessons()->create(['course_session_id' => $one->id, 'title' => 'Breathing', 'kind' => 'text', 'body' => 'Breathe low.', 'sort' => 0, 'is_preview' => true]);
        $course->lessons()->create(['course_session_id' => $one->id, 'title' => 'Projection', 'kind' => 'text', 'body' => 'Speak to the back row.', 'sort' => 1]);
        $course->lessons()->create(['course_session_id' => $two->id, 'title' => 'Wants', 'kind' => 'text', 'body' => 'What does the character want?', 'sort' => 0]);

        return $course->fresh();
    }

    protected function lesson(Course $course, string $title): Lesson
    {
        return $course->lessons()->where('title', $title)->firstOrFail();
    }

    public function test_coordinators_create_training_only_for_their_own_area(): void
    {
        $form = ['title' => 'Lagos drama clinic', 'kind' => 'live', 'summary' => 'A clinic for Lagos teams.'];

        $this->actingAs($this->users['lagos'])->post(route('academy.manage.store'), $form + ['org_unit_id' => $this->lagos->id])
            ->assertRedirect();
        $course = Course::firstWhere('title', 'Lagos drama clinic');
        $this->assertSame('draft', $course->status);

        $this->actingAs($this->users['lagos'])->post(route('academy.manage.store'), $form + ['org_unit_id' => $this->ibadan->id])->assertForbidden();
        $this->actingAs($this->users['region'])->post(route('academy.manage.store'), $form + ['org_unit_id' => $this->lagos->id, 'target_units' => [$this->ibadan->id]])
            ->assertSessionHasErrors('target_units');
        $this->actingAs($this->users['agege'])->get(route('academy.manage.create'))->assertForbidden();

        // Cannot open an empty course.
        $this->actingAs($this->users['lagos'])->post(route('academy.manage.status', $course), ['status' => 'published'])->assertSessionHasErrors('status');
    }

    public function test_facilitators_are_added_by_member_id(): void
    {
        $teacher = $this->person($this->mushin, 'Tola', 'Teacher', password: 'secret-pass-1');
        $course = $this->course();

        $this->actingAs($this->users['lagos'])->put(route('academy.manage.update', $course), [
            'title' => $course->title, 'kind' => 'blended', 'org_unit_id' => $this->lagos->id, 'summary' => $course->summary,
            'facilitators' => $teacher->member_no.', Voice coach',
        ])->assertRedirect();
        $this->assertDatabaseHas('course_facilitators', ['course_id' => $course->id, 'member_id' => $teacher->id, 'title' => 'Voice coach']);

        $this->actingAs($teacher->user)->get(route('academy.manage.build', $course))->assertOk();
        $this->actingAs($teacher->user)->get(route('academy.manage.edit', $course))->assertForbidden();

        $this->actingAs($this->users['lagos'])->put(route('academy.manage.update', $course), [
            'title' => $course->title, 'kind' => 'blended', 'org_unit_id' => $this->lagos->id, 'summary' => $course->summary, 'facilitators' => 'GDM-999999',
        ])->assertSessionHasErrors('facilitators');
    }

    public function test_who_can_see_which_training(): void
    {
        $lagosCourse = $this->course();
        $draft = $this->course(['title' => 'Not ready', 'status' => 'draft']);
        $forCoordinators = $this->course(['title' => 'Coordinator guide', 'org_unit_id' => $this->national->id]);
        $forCoordinators->targets()->create(['audience' => 'role', 'role_key' => 'assembly_coordinator']);
        $public = $this->course(['title' => 'Open to all', 'org_unit_id' => $this->national->id, 'is_public' => true]);

        $agegeMember = $this->member();
        $mokolaMember = $this->member('Ade', $this->mokola);

        $this->actingAs($agegeMember)->get(route('academy'))->assertSee('Acting basics')->assertDontSee('Not ready')->assertDontSee('Coordinator guide');
        $this->actingAs($mokolaMember)->get(route('academy.show', $lagosCourse))->assertNotFound();
        $this->actingAs($this->users['mokola'])->get(route('academy.show', $forCoordinators))->assertOk();
        $this->actingAs($agegeMember)->get(route('academy.show', $forCoordinators))->assertNotFound();
        $this->actingAs($agegeMember)->get(route('academy.show', $draft))->assertNotFound();

        auth()->logout();
        $this->get(route('academy'))->assertSee('Open to all')->assertDontSee('Acting basics');
        $this->get(route('academy.show', $public))->assertOk()->assertSee('Sign in to enrol');
    }

    public function test_enrol_learn_and_complete(): void
    {
        $course = $this->course();
        $learner = $this->member();
        [$breathing, $projection, $wants] = [$this->lesson($course, 'Breathing'), $this->lesson($course, 'Projection'), $this->lesson($course, 'Wants')];

        // Preview lessons are open; the rest wait for enrolment.
        $this->actingAs($learner)->get(route('academy.lesson', [$course, $breathing]))->assertOk()->assertSee('Breathe low.');
        $this->actingAs($learner)->get(route('academy.lesson', [$course, $projection]))->assertRedirect(route('academy.show', $course));

        $this->actingAs($learner)->post(route('academy.enrol', $course))->assertRedirect(route('academy.lesson', [$course, $breathing]));
        $this->actingAs($learner)->get(route('academy.lesson', [$course, $projection]))->assertOk()->assertSee('Speak to the back row.');

        $this->actingAs($learner)->post(route('academy.complete', [$course, $breathing]))->assertRedirect(route('academy.lesson', [$course, $projection]));
        $this->actingAs($learner)->post(route('academy.complete', [$course, $projection]));
        $this->actingAs($learner)->get(route('academy.show', $course))->assertSee('2 / 3');
        $this->actingAs($learner)->get(route('academy.mine'))->assertSee('2 / 3 lessons completed');

        $this->actingAs($learner)->post(route('academy.complete', [$course, $wants]))->assertRedirect(route('academy.show', $course));
        $enrolment = Enrolment::firstOrFail();
        $this->assertSame('completed', $enrolment->status);
        $this->assertNotNull($enrolment->completed_at);

        // A lesson from another course cannot be reached through this one.
        $other = $this->course(['title' => 'Other']);
        $this->actingAs($learner)->get('/academy/'.$course->slug.'/lessons/'.$this->lesson($other, 'Projection')->id)->assertNotFound();
    }

    public function test_enrolment_closes(): void
    {
        $course = $this->course(['enrol_by' => now()->subDay()->toDateString()]);
        $this->actingAs($this->member())->post(route('academy.enrol', $course))->assertSessionHasErrors('enrol');
        $this->assertSame(0, Enrolment::count());
    }

    public function test_call_and_response(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course, 'Breathing');
        $fill = $course->prompts()->create(['lesson_id' => $lesson->id, 'type' => 'complete', 'question' => 'Speak to the back ___', 'answer' => 'row|seat']);
        $poll = $course->prompts()->create(['lesson_id' => $lesson->id, 'type' => 'poll', 'question' => 'Favourite?', 'options' => ['Voice', 'Movement']]);
        $learner = $this->member();
        $this->actingAs($learner)->post(route('academy.enrol', $course));

        $this->actingAs($learner)->postJson(route('academy.respond', [$course, $fill]), ['response' => ' Row! '])->assertJson(['correct' => true]);
        $this->actingAs($learner)->postJson(route('academy.respond', [$course, $fill]), ['response' => 'stage'])->assertJson(['correct' => false, 'answer' => 'row']);
        $this->actingAs($learner)->postJson(route('academy.respond', [$course, $poll]), ['response' => 'Voice'])->assertJson(['tally' => ['Voice' => 1, 'Movement' => 0]]);
        $this->actingAs($learner)->postJson(route('academy.respond', [$course, $poll]), ['response' => 'Dance'])->assertStatus(422);
        $this->assertSame(2, $learner->fresh()->id ? \App\Models\PromptResponse::where('user_id', $learner->id)->count() : 0);
    }

    public function test_live_room_marks_attendance_honestly(): void
    {
        $course = $this->course();
        $live = $course->sessions()->whereNotNull('live_at')->first();
        $later = $course->sessions()->create(['title' => 'Later', 'sort' => 2, 'live_at' => now()->addDays(3)]);
        $learner = $this->member();
        $outsider = $this->member('Yemi', $this->mushin);

        $this->actingAs($outsider)->get(route('academy.room', [$course, $live]))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('academy.feed', [$course, $live]))->assertForbidden();

        $this->actingAs($learner)->post(route('academy.enrol', $course));
        $this->actingAs($learner)->get(route('academy.room', [$course, $later]))->assertOk();
        $this->assertDatabaseMissing('session_attendance', ['course_session_id' => $later->id]);

        $this->actingAs($learner)->get(route('academy.room', [$course, $live]))->assertOk()->assertSee('You are marked as joined');
        $this->assertDatabaseHas('session_attendance', ['course_session_id' => $live->id, 'member_id' => $learner->member_id, 'status' => 'joined']);

        $prompt = $course->prompts()->create(['course_session_id' => $live->id, 'type' => 'poll', 'question' => 'Ready?', 'options' => ['Yes', 'No'], 'is_live' => true]);
        $this->actingAs($learner)->getJson(route('academy.feed', [$course, $live]))->assertOk()
            ->assertJsonPath('joined', 1)->assertJsonPath('prompts.0.question', 'Ready?')->assertJsonPath('prompts.0.tally', null);

        $this->actingAs($this->users['lagos'])->post(route('academy.manage.sessions.attendance', [$course, $live]), ['status' => [$learner->member_id => 'attended']])->assertRedirect();
        $this->assertDatabaseHas('session_attendance', ['course_session_id' => $live->id, 'member_id' => $learner->member_id, 'status' => 'attended', 'confirmed_by' => $this->users['lagos']->id]);
    }

    public function test_questions_stay_private_until_answered(): void
    {
        $course = $this->course();
        [$asker, $classmate] = [$this->member('Asker'), $this->member('Classmate')];
        foreach ([$asker, $classmate] as $u) {
            $this->actingAs($u)->post(route('academy.enrol', $course));
        }

        $this->actingAs($asker)->post(route('academy.ask', $course), ['body' => 'Can we mix Yoruba and English?'])->assertRedirect();
        $question = CourseQuestion::firstOrFail();
        $this->actingAs($classmate)->get(route('academy.show', $course))->assertDontSee('Can we mix Yoruba and English?');
        $this->actingAs($asker)->get(route('academy.show', $course))->assertSee('Can we mix Yoruba and English?');

        $this->actingAs($classmate)->post(route('academy.manage.questions.moderate', [$course, $question]), ['action' => 'answer', 'answer' => 'No'])->assertForbidden();
        $this->actingAs($this->users['lagos'])->post(route('academy.manage.questions.moderate', [$course, $question]), ['action' => 'answer', 'answer' => 'Yes, where it serves the audience.']);
        $this->actingAs($classmate)->get(route('academy.show', $course))->assertSee('Yes, where it serves the audience.');
    }

    public function test_assignments_are_handed_in_and_reviewed(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $assignment = $course->assignments()->create(['title' => 'Write a scene', 'brief' => 'One page.', 'accepts' => ['text', 'file'], 'max_score' => 20]);
        $learner = $this->member();
        $classmate = $this->member('Classmate');

        $this->actingAs($learner)->post(route('academy.submit', [$course, $assignment]), ['body' => 'Scene'])->assertForbidden();
        $this->actingAs($learner)->post(route('academy.enrol', $course));
        $this->actingAs($learner)->post(route('academy.submit', [$course, $assignment]), [])->assertSessionHasErrors('body');
        $this->actingAs($learner)->post(route('academy.submit', [$course, $assignment]), [
            'body' => 'MARKET DAY', 'file' => UploadedFile::fake()->create('scene.docx', 40, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect();
        $submission = AssignmentSubmission::firstOrFail();

        $this->actingAs($learner)->get(route('academy.submission-file', $submission))->assertOk();
        $this->actingAs($classmate)->get(route('academy.submission-file', $submission))->assertForbidden();
        $this->actingAs($this->users['lagos'])->get(route('academy.submission-file', $submission))->assertOk();

        $this->actingAs($this->users['mokola'])->post(route('academy.manage.submissions.review', [$course, $submission]), ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($this->users['lagos'])->post(route('academy.manage.submissions.review', [$course, $submission]), ['status' => 'returned'])->assertSessionHasErrors('feedback');
        $this->actingAs($this->users['lagos'])->post(route('academy.manage.submissions.review', [$course, $submission]), ['status' => 'accepted', 'feedback' => 'Strong opening.', 'score' => 17])->assertRedirect();

        $this->actingAs($learner)->get(route('academy.assignment', [$course, $assignment]))->assertSee('Strong opening.')->assertSee('17');
        $this->actingAs($learner)->post(route('academy.submit', [$course, $assignment]), ['body' => 'Changed'])->assertStatus(422);
    }

    public function test_resources_need_enrolment(): void
    {
        Storage::fake('local');
        $course = $this->course();
        $this->actingAs($this->users['lagos'])->post(route('academy.manage.resources.store', $course), [
            'title' => 'Script', 'file' => UploadedFile::fake()->create('script.pdf', 30, 'application/pdf'),
        ])->assertRedirect();
        $resource = $course->resources()->firstOrFail();
        $learner = $this->member();

        $this->actingAs($learner)->get(route('academy.resource', [$course, $resource]))->assertForbidden();
        $this->actingAs($learner)->post(route('academy.enrol', $course));
        $this->actingAs($learner)->get(route('academy.resource', [$course, $resource]))->assertOk()->assertDownload('script.pdf');
    }

    public function test_area_overview_counts_only_members_in_the_area(): void
    {
        $course = $this->course(['org_unit_id' => $this->national->id]);
        $this->actingAs($this->member('Lagos1'))->post(route('academy.enrol', $course));
        $this->actingAs($this->member('Lagos2', $this->mushin))->post(route('academy.enrol', $course));
        $this->actingAs($this->member('Ibadan1', $this->mokola))->post(route('academy.enrol', $course));

        $rows = app(\App\Services\Academy::class)->overview([$this->lagos->path]);
        $this->assertSame(2, $rows->firstWhere('course.id', $course->id)->enrolled);

        $this->actingAs($this->users['lagos'])->get(route('academy.overview'))->assertOk()->assertSee('Lagos1 Learner')->assertDontSee('Ibadan1 Learner');
        $this->actingAs($this->member('Plain'))->get(route('academy.overview'))->assertForbidden();
    }

    public function test_building_a_lesson_with_call_and_response(): void
    {
        $course = $this->course();
        $session = $course->sessions()->first();

        $this->actingAs($this->users['lagos'])->post(route('academy.manage.lessons.store', $course), [
            'title' => 'Video lesson', 'course_session_id' => $session->id, 'kind' => 'video', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ])->assertRedirect();
        $this->assertDatabaseHas('lessons', ['title' => 'Video lesson', 'youtube_id' => 'dQw4w9WgXcQ']);

        $this->actingAs($this->users['lagos'])->post(route('academy.manage.prompts.store', $course), [
            'type' => 'choice', 'question' => 'Which?', 'options' => "A\nB", 'answer' => 'C',
        ])->assertSessionHasErrors('answer');

        // A session from another course cannot be used.
        $other = $this->course(['title' => 'Other']);
        $this->actingAs($this->users['lagos'])->post(route('academy.manage.lessons.store', $course), [
            'title' => 'Sneaky', 'course_session_id' => $other->sessions()->first()->id, 'kind' => 'text',
        ])->assertSessionHasErrors('course_session_id');
    }
}
