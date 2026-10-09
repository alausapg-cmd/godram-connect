<?php

namespace Tests\Feature;

use App\Models\AchievementRule;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\MemberAchievement;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Services\Achievements;
use App\Services\Cbt;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class CbtTest extends TestCase
{
    use BuildsMinistry;

    protected QuestionCategory $acting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
        $this->acting = QuestionCategory::create(['name' => 'Acting']);
        foreach (range(1, 6) as $i) {
            $this->question('single', ['options' => [['key' => 'a', 'text' => "Right $i"], ['key' => 'b', 'text' => "Wrong $i"], ['key' => 'c', 'text' => "Also wrong $i"]], 'answer' => ['key' => 'a']], "Question $i?");
        }
    }

    protected function question(string $type, array $values, string $stem = 'A question?', string $status = 'approved'): Question
    {
        return Question::create($values + ['question_category_id' => $this->acting->id, 'type' => $type, 'stem' => $stem, 'status' => $status, 'marks' => 1]);
    }

    protected function exam(array $values = [], int $count = 4): Exam
    {
        $exam = Exam::create($values + ['title' => 'Acting certification', 'org_unit_id' => $this->lagos->id, 'status' => 'published', 'duration_minutes' => 20, 'pass_mark' => 70, 'awards_certificate' => true]);
        $exam->blueprint()->create(['question_category_id' => $this->acting->id, 'count' => $count]);

        return $exam->fresh();
    }

    protected function candidate(string $first = 'Ngozi', $unit = null): User
    {
        return $this->person($unit ?? $this->agege, $first, 'Candidate', password: 'secret-pass-1')->user;
    }

    /** Answer every question on the paper, right or wrong. */
    protected function answerAll(User $user, ExamAttempt $attempt, int $right): void
    {
        foreach ($attempt->questions as $i => $item) {
            $this->actingAs($user)->postJson(route('exams.save', $attempt), ['position' => $item->position, 'revision' => 1, 'response' => $i < $right ? 'a' : 'b'])->assertOk();
        }
    }

    public function test_question_types_are_marked_on_the_server(): void
    {
        $s = fn ($type, $answer, $options = null) => ['type' => $type, 'answer' => $answer, 'options' => $options, 'marks' => 2.0];

        $this->assertSame([true, 2.0], Question::grade($s('multiple', ['keys' => ['a', 'c']]), ['c', 'a']));
        $this->assertSame([false, 0.0], Question::grade($s('multiple', ['keys' => ['a', 'c']]), ['a']));
        $this->assertSame([true, 2.0], Question::grade($s('true_false', ['value' => false]), false));
        $this->assertSame([true, 2.0], Question::grade($s('fill_blank', ['accepted' => ['Curtain raiser']]), '  curtain-raiser! '));
        $this->assertSame([true, 2.0], Question::grade($s('ordering', ['a', 'b', 'c']), ['a', 'b', 'c']));
        $this->assertSame([false, 0.0], Question::grade($s('ordering', ['a', 'b', 'c']), ['b', 'a', 'c']));
        $matching = $s('matching', null, ['left' => [['key' => 'a', 'text' => 'SM'], ['key' => 'b', 'text' => 'Props']], 'right' => []]);
        $this->assertSame([true, 2.0], Question::grade($matching, ['a' => 'a', 'b' => 'b']));
        $this->assertSame([false, 1.0], Question::grade($matching, ['a' => 'a', 'b' => 'a']));
        $this->assertSame([null, null], Question::grade($s('open', null), 'An essay'));
        $this->assertSame([false, 0.0], Question::grade($s('short', ['accepted' => ['x']]), ''));
    }

    public function test_a_candidate_sits_the_exam_without_ever_seeing_the_answers(): void
    {
        $exam = $this->exam();
        $user = $this->candidate();

        $this->actingAs($user)->get(route('exams.index'))->assertOk()->assertSee('Acting certification');
        $this->actingAs($user)->post(route('exams.start', $exam))->assertRedirect();
        $attempt = ExamAttempt::firstOrFail();
        $this->assertCount(4, $attempt->questions);
        $this->assertCount(4, $attempt->questions->pluck('question_id')->unique());

        $page = $this->actingAs($user)->get(route('exams.sit', $attempt))->assertOk()->getContent();
        $this->assertStringNotContainsString('"answer"', $page);
        $this->assertStringNotContainsString('explanation', $page);

        // Starting again resumes the same attempt.
        $this->actingAs($user)->post(route('exams.start', $exam))->assertRedirect(route('exams.sit', $attempt));
        $this->assertSame(1, ExamAttempt::count());

        $this->answerAll($user, $attempt, 3);
        $this->actingAs($user)->post(route('exams.submit', $attempt))->assertRedirect(route('exams.result', $attempt));
        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertEquals(75.0, $attempt->percent);
        $this->assertTrue($attempt->passed);
        $this->assertSame([3, 1, 0], [$attempt->correct, $attempt->incorrect, $attempt->unanswered]);

        $certificate = Certificate::firstOrFail();
        $this->assertSame($user->member_id, $certificate->member_id);
        $this->assertStringStartsWith('GODRAM-CERT-'.now()->year.'-000001', $certificate->number);
        $this->assertStringContainsString('75%', $certificate->eligibility_rule);
        $this->actingAs($user)->get(route('exams.result', $attempt))->assertOk()->assertSee('Passed')->assertSee($certificate->number)
            ->assertDontSee('Correct answer');
    }

    public function test_saves_use_revisions_and_reject_impossible_answers(): void
    {
        $exam = $this->exam();
        $user = $this->candidate();
        $attempt = app(Cbt::class)->start($exam, $user->member);

        $url = route('exams.save', $attempt);
        $this->actingAs($user)->postJson($url, ['position' => 1, 'revision' => 2, 'response' => 'b'])->assertOk();
        // An older save that arrives late does not overwrite the newer answer.
        $this->actingAs($user)->postJson($url, ['position' => 1, 'revision' => 1, 'response' => 'a'])->assertOk()->assertJson(['stale' => true]);
        $this->assertSame('b', $attempt->questions()->where('position', 1)->first()->response);
        $this->actingAs($user)->postJson($url, ['position' => 1, 'revision' => 3, 'response' => 'z'])->assertStatus(422);

        $this->actingAs($user)->postJson($url, ['position' => 2, 'revision' => 1, 'response' => 'a', 'flagged' => true])->assertOk();
        $this->assertTrue($attempt->questions()->where('position', 2)->first()->is_flagged);
        $this->assertDatabaseHas('attempt_events', ['exam_attempt_id' => $attempt->id, 'type' => 'flagged']);

        // Someone else cannot touch this paper.
        $this->actingAs($this->candidate('Other'))->postJson($url, ['position' => 1, 'revision' => 9, 'response' => 'a'])->assertNotFound();
        $this->actingAs($this->candidate('Third'))->get(route('exams.sit', $attempt))->assertNotFound();
    }

    public function test_the_server_clock_ends_the_attempt(): void
    {
        $exam = $this->exam(['duration_minutes' => 10]);
        $user = $this->candidate();
        $attempt = app(Cbt::class)->start($exam, $user->member);
        $this->actingAs($user)->postJson(route('exams.save', $attempt), ['position' => 1, 'revision' => 1, 'response' => 'a'])->assertOk();

        Carbon::setTestNow(now()->addMinutes(11));
        $this->actingAs($user)->postJson(route('exams.save', $attempt), ['position' => 2, 'revision' => 1, 'response' => 'a'])
            ->assertStatus(409)->assertJson(['reason' => 'time']);
        $attempt->refresh();
        $this->assertSame('auto_submitted', $attempt->status);
        $this->assertSame(1, $attempt->correct);
        $this->assertSame(600, $attempt->time_used_seconds);
        Carbon::setTestNow();
    }

    public function test_abandoned_attempts_are_submitted_by_the_scheduler(): void
    {
        $exam = $this->exam(['duration_minutes' => 5]);
        $attempt = app(Cbt::class)->start($exam, $this->candidate()->member);
        Carbon::setTestNow(now()->addMinutes(7));
        $this->artisan('godram:finalise-exams')->assertSuccessful();
        $this->assertSame('auto_submitted', $attempt->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_eligibility_attempts_and_course_completion(): void
    {
        $exam = $this->exam(['max_attempts' => 1]);
        $outsider = $this->candidate('Bola', $this->mokola);
        $this->actingAs($outsider)->post(route('exams.start', $exam))->assertSessionHasErrors('exam');

        $user = $this->candidate();
        $attempt = app(Cbt::class)->start($exam, $user->member);
        app(Cbt::class)->submit($attempt);
        $this->actingAs($user)->post(route('exams.start', $exam))->assertSessionHasErrors('exam');

        $course = Course::create(['title' => 'Acting basics', 'kind' => 'recorded', 'org_unit_id' => $this->lagos->id, 'summary' => 'x', 'status' => 'published']);
        $final = $this->exam(['title' => 'Final', 'course_id' => $course->id, 'requires_course_completion' => true]);
        $learner = $this->candidate('Tayo');
        $this->assertStringContainsString('Enrol', $final->eligibility($learner->member)[1]);
        $enrolment = $course->enrolments()->create(['member_id' => $learner->member_id, 'status' => 'active']);
        $this->assertStringContainsString('Complete every lesson', $final->eligibility($learner->member)[1]);
        $enrolment->update(['status' => 'completed']);
        $this->assertTrue($final->eligibility($learner->member)[0]);
    }

    public function test_results_held_until_release_and_written_answers_marked(): void
    {
        $this->question('open', [], 'Describe your opening scene.');
        $exam = Exam::create(['title' => 'Written', 'org_unit_id' => $this->lagos->id, 'status' => 'published', 'release' => 'manual', 'pass_mark' => 50, 'awards_certificate' => true, 'shuffle_questions' => false]);
        $exam->blueprint()->create(['question_category_id' => $this->acting->id, 'count' => 7]);
        $user = $this->candidate();
        $attempt = app(Cbt::class)->start($exam, $user->member);
        foreach ($attempt->questions as $item) {
            $response = $item->snapshot['type'] === 'open' ? 'The market scene opens it.' : 'a';
            app(Cbt::class)->save($attempt, $item->position, $response, 1);
        }
        app(Cbt::class)->submit($attempt);
        $attempt->refresh();
        $this->assertTrue($attempt->needs_marking);
        $this->assertNull($attempt->passed);
        $this->actingAs($user)->get(route('exams.result', $attempt))->assertOk()->assertSee('Your answers are in')->assertDontSee('%</span>', false);

        $open = $attempt->questions->first(fn ($i) => $i->snapshot['type'] === 'open');
        $this->actingAs($this->users['ibadan'])->post(route('exams.manage.mark', [$exam, $attempt]), ['marks' => [$open->position => 1]])->assertForbidden();
        $this->actingAs($this->users['lagos'])->post(route('exams.manage.mark', [$exam, $attempt]), ['marks' => [$open->position => 0.5]])->assertRedirect();
        $attempt->refresh();
        $this->assertFalse($attempt->needs_marking);
        $this->assertTrue($attempt->passed);
        $this->assertSame(0, Certificate::count());

        $this->actingAs($this->users['lagos'])->post(route('exams.manage.release', $exam))->assertRedirect();
        $this->assertSame(1, Certificate::count());
        $this->actingAs($user)->get(route('exams.result', $attempt))->assertSee('Passed');
    }

    public function test_practice_shows_answers_and_never_issues_certificates(): void
    {
        $exam = $this->exam(['mode' => 'practice', 'awards_certificate' => true]);
        $user = $this->candidate();
        $attempt = app(Cbt::class)->start($exam, $user->member);
        $this->answerAll($user, $attempt, 4);
        app(Cbt::class)->submit($attempt);
        $this->assertSame(0, Certificate::count());
        $this->actingAs($user)->get(route('exams.result', $attempt))->assertSee('Your answers');
    }

    public function test_exam_builder_is_limited_to_the_area_and_checks_the_pool(): void
    {
        $form = ['title' => 'Lagos exam', 'mode' => 'certification', 'duration_minutes' => 20, 'question_count' => 5, 'pass_mark' => 70,
            'result_policy' => 'best', 'release' => 'immediate', 'rows' => [['category' => $this->acting->id, 'difficulty' => '', 'count' => 10]]];
        $this->actingAs($this->users['lagos'])->post(route('exams.manage.store'), $form + ['org_unit_id' => $this->ibadan->id])->assertSessionHasErrors('org_unit_id');
        $this->actingAs($this->users['agege'])->get(route('exams.manage.index'))->assertForbidden();

        $this->actingAs($this->users['lagos'])->post(route('exams.manage.store'), $form + ['org_unit_id' => $this->lagos->id])->assertRedirect();
        $exam = Exam::firstWhere('title', 'Lagos exam');
        $this->assertSame('draft', $exam->status);
        $this->assertSame(10, $exam->question_count);
        // Only six approved questions exist, so it cannot be published.
        $this->actingAs($this->users['lagos'])->post(route('exams.manage.status', $exam), ['status' => 'published'])->assertSessionHasErrors('exam');
        $this->actingAs($this->users['ibadan'])->get(route('exams.manage.show', $exam))->assertForbidden();
        $this->actingAs($this->users['lagos'])->get(route('exams.manage.show', $exam))->assertOk();
    }

    public function test_editing_an_approved_question_makes_a_new_version_for_review(): void
    {
        $question = Question::first();
        $form = ['type' => 'single', 'question_category_id' => $this->acting->id, 'difficulty' => 'easy', 'stem' => 'Reworded?', 'marks' => 1,
            'options' => ['Right', 'Wrong'], 'correct' => ['0']];
        $this->actingAs($this->users['lagos'])->put(route('questions.update', $question), $form)->assertRedirect();
        $question->refresh();
        $this->assertSame(['draft', 2], [$question->status, $question->version]);

        // District coordinators write questions but do not approve them.
        $this->actingAs($this->users['lagos'])->post(route('questions.review', $question), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->users['national'])->post(route('questions.review', $question), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $question->fresh()->status);

        $this->actingAs($this->users['lagos'])->post(route('questions.store'), ['options' => ['Only one']] + $form)->assertSessionHasErrors('options');
    }

    public function test_certificates_are_verified_publicly_with_limited_details(): void
    {
        $exam = $this->exam();
        $user = $this->candidate();
        $attempt = app(Cbt::class)->start($exam, $user->member);
        $this->answerAll($user, $attempt, 4);
        app(Cbt::class)->submit($attempt);
        $certificate = Certificate::firstOrFail();

        $this->get(route('certificates.verify', strtolower($certificate->number)))->assertOk()
            ->assertSee('genuine')->assertSee('Ngozi C.')->assertDontSee($user->member->member_no)->assertDontSee('Agege');
        $this->get(route('certificates.verify', 'GODRAM-CERT-1999-000001'))->assertNotFound();

        $this->actingAs($user)->get(route('certificates.download', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->candidate('Other'))->get(route('certificates.download', $certificate))->assertNotFound();

        $this->actingAs($this->users['lagos'])->post(route('certificates.manage.revoke', $certificate), ['reason' => 'Issued in error'])->assertForbidden();
        $this->actingAs($this->users['national'])->post(route('certificates.manage.revoke', $certificate), ['reason' => 'Issued in error'])->assertRedirect();
        auth()->logout();
        $this->get(route('certificates.verify', $certificate->number))->assertOk()->assertSee('revoked');
    }

    public function test_achievements_are_awarded_once_with_their_certificate(): void
    {
        $member = $this->candidate()->member;
        $member->update(['joined_on' => now()->subYears(11)]);
        AchievementRule::create(['name' => 'Ten years of service', 'kind' => 'service', 'metric' => 'years_of_service', 'threshold' => 10, 'issues_certificate' => true]);
        AchievementRule::create(['name' => 'Twenty years of service', 'kind' => 'service', 'metric' => 'years_of_service', 'threshold' => 20]);

        $this->assertSame(1, app(Achievements::class)->evaluate($member));
        $this->assertSame(0, app(Achievements::class)->evaluate($member));
        $this->assertSame(1, MemberAchievement::where('member_id', $member->id)->count());
        $this->assertSame('achievement', Certificate::firstOrFail()->kind);
        $this->actingAs($member->user)->get(route('certificates.mine'))->assertOk()->assertSee('Ten years of service')->assertSee('Twenty years of service');
    }

    public function test_each_kind_of_certificate_has_its_own_design(): void
    {
        foreach (array_keys(Certificate::KINDS) as $kind) {
            $this->assertTrue(view()->exists('certificates.designs.'.$kind), $kind);
            $this->actingAs($this->users['national'])->get(route('certificates.manage.specimen', $kind))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->actingAs($this->users['lagos'])->get(route('certificates.manage.specimen', 'training'))->assertForbidden();
        $this->actingAs($this->users['national'])->get(route('certificates.manage.specimen', 'other'))->assertNotFound();
    }
}
