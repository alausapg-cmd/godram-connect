<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    protected function submittedAgegeReport(): ActivityReport
    {
        $this->actingAs($this->users['agege'])->post(route('reports.start'), ['org_unit_id' => $this->agege->id])->assertRedirect();
        $report = ActivityReport::latest('id')->firstOrFail();

        $this->actingAs($this->users['agege'])->put(route('reports.update', $report), [
            'action' => 'submit',
            'activity_type' => 'evangelistic_performance',
            'title' => 'Market square outreach',
            'activity_date' => now()->subDays(2)->toDateString(),
            'location' => 'Agege market',
            'description' => 'Drama ministration and altar call.',
            'performers_count' => 12,
            'attendance_count' => 300,
            'souls_won' => 14,
        ])->assertRedirect(route('reports.show', $report));

        return $report->fresh();
    }

    public function test_assembly_report_goes_from_draft_to_approved_by_the_district(): void
    {
        $report = $this->submittedAgegeReport();
        $this->assertSame('submitted', $report->status);
        $this->assertMatchesRegularExpression('/^RPT-\d{4}-\d{6}$/', $report->reference);

        // The author cannot review it, and nor can a different District.
        $this->actingAs($this->users['agege'])->post(route('reports.review', $report), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->users['ibadan'])->post(route('reports.review', $report), ['decision' => 'approve'])->assertForbidden();

        // Regional and National Coordinators can view and comment, but not approve an Assembly report.
        foreach (['region', 'national'] as $who) {
            $this->actingAs($this->users[$who])->get(route('reports.show', $report))->assertOk();
            $this->actingAs($this->users[$who])->post(route('reports.review', $report), ['decision' => 'approve'])->assertForbidden();
            $this->actingAs($this->users[$who])->put(route('reports.update', $report), ['title' => 'Changed'])->assertForbidden();
        }
        $this->actingAs($this->users['region'])->post(route('reports.comment', $report), ['comment' => 'Well done, Agege.'])->assertRedirect();

        $this->actingAs($this->users['lagos'])->post(route('reports.review', $report), ['decision' => 'start'])->assertRedirect();
        $this->assertSame('under_review', $report->fresh()->status);
        $this->actingAs($this->users['lagos'])->post(route('reports.review', $report), ['decision' => 'approve'])->assertRedirect();

        $report->refresh();
        $this->assertSame('approved', $report->status);
        $this->assertSame($this->users['lagos']->id, $report->reviewer_id);
        $this->assertSame(['submitted', 'commented', 'review_started', 'approved'], $report->events()->reorder('id')->pluck('action')->all());
        $this->assertSame(1, ActivityReport::counted()->count());
    }

    public function test_returned_report_needs_a_reason_and_can_be_corrected_and_resubmitted(): void
    {
        $report = $this->submittedAgegeReport();

        $this->actingAs($this->users['lagos'])->post(route('reports.review', $report), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->actingAs($this->users['lagos'])->post(route('reports.review', $report), ['decision' => 'reject', 'note' => 'Please add a photo.']);
        $this->assertSame('rejected', $report->fresh()->status);
        $this->assertSame('Please add a photo.', $report->fresh()->reject_reason);

        $this->actingAs($this->users['agege'])->get(route('reports.edit', $report))->assertOk()->assertSee('Please add a photo.');
        $this->actingAs($this->users['agege'])->post(route('reports.submit', $report))->assertRedirect();
        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertSame('resubmitted', $report->events()->reorder('id', 'desc')->value('action'));
    }

    public function test_submitted_report_is_locked_for_the_author(): void
    {
        $report = $this->submittedAgegeReport();
        $this->actingAs($this->users['agege'])->put(route('reports.update', $report), ['title' => 'Edited'])->assertForbidden();
    }

    public function test_incomplete_report_cannot_be_submitted(): void
    {
        $this->actingAs($this->users['agege'])->post(route('reports.start'), ['org_unit_id' => $this->agege->id]);
        $report = ActivityReport::latest('id')->firstOrFail();

        $this->actingAs($this->users['agege'])->post(route('reports.submit', $report))->assertSessionHasErrors('report');
        $this->assertSame('draft', $report->fresh()->status);
    }

    public function test_drafts_autosave_and_are_private_to_the_author(): void
    {
        $this->actingAs($this->users['agege'])->post(route('reports.start'), ['org_unit_id' => $this->agege->id]);
        $report = ActivityReport::latest('id')->firstOrFail();

        $this->actingAs($this->users['agege'])->patchJson(route('reports.autosave', $report), ['title' => 'Half written'])->assertOk()->assertJsonStructure(['saved_at']);
        $this->assertSame('Half written', $report->fresh()->title);
        $this->actingAs($this->users['lagos'])->get(route('reports.index'))->assertDontSee('Half written');
    }

    public function test_district_report_is_approved_by_the_region_and_published_by_the_district(): void
    {
        $this->actingAs($this->users['lagos'])->post(route('reports.start'), ['org_unit_id' => $this->lagos->id]);
        $report = ActivityReport::latest('id')->firstOrFail();
        $report->update(['activity_type' => 'workshop', 'title' => 'District workshop', 'location' => 'HQ', 'description' => 'Voice and movement.']);
        $this->actingAs($this->users['lagos'])->post(route('reports.submit', $report));

        $this->actingAs($this->users['national'])->post(route('reports.review', $report), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->users['region'])->post(route('reports.review', $report), ['decision' => 'approve'])->assertRedirect();

        $this->actingAs($this->users['lagos'])->post(route('reports.publish', $report))->assertRedirect();
        $this->assertSame('published', $report->fresh()->status);
        $this->get(route('highlights'))->assertOk()->assertSee('District workshop');
    }

    public function test_report_photos_are_private_until_published(): void
    {
        $report = $this->submittedAgegeReport();
        // Upload is allowed while the report is editable, so return it first.
        $this->actingAs($this->users['lagos'])->post(route('reports.review', $report), ['decision' => 'reject', 'note' => 'Add a photo']);

        $file = UploadedFile::fake()->image('stage.jpg', 1600, 1000);
        $this->actingAs($this->users['agege'])->post(route('report-media.store', $report), ['files' => [$file]])->assertRedirect();
        $media = $report->media()->firstOrFail();
        $this->assertStringEndsWith('.webp', $media->path);

        auth()->logout();
        $this->get(route('report-media.show', $media))->assertForbidden();
        $this->actingAs($this->users['ibadan'])->get(route('report-media.show', $media))->assertForbidden();
        $this->actingAs($this->users['lagos'])->get(route('report-media.show', $media))->assertOk();
    }

    public function test_a_member_named_on_reports_only_gets_links_they_can_open(): void
    {
        $member = $this->person($this->agege, 'Tola', 'Member', password: 'secret-pass-1');
        $make = fn (string $title, string $status) => ActivityReport::create([
            'org_unit_id' => $this->agege->id, 'title' => $title, 'activity_type' => 'workshop', 'status' => $status,
            'activity_date' => now()->subWeek()->toDateString(), 'created_by' => $this->users['agege']->id,
        ]);
        $published = $make('Acting workshop', 'published');
        $approved = $make('Rehearsal weekend', 'approved');
        foreach ([$published, $approved] as $report) {
            $report->participants()->attach($member->id, ['role' => 'performer']);
        }

        $this->actingAs($member->user)->get(route('members.show', $member))->assertOk()
            ->assertSee('Rehearsal weekend')
            ->assertSee(route('highlights.show', $published))
            ->assertDontSee(route('reports.show', $published))
            ->assertDontSee(route('reports.show', $approved));

        $this->actingAs($this->users['agege'])->get(route('members.show', $member))->assertOk()
            ->assertSee(route('reports.show', $approved));
    }
}
