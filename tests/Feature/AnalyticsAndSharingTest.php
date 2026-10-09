<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Event;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class AnalyticsAndSharingTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    public function test_analytics_follow_the_leaders_place_in_the_tree(): void
    {
        ActivityReport::create(['org_unit_id' => $this->agege->id, 'activity_type' => 'drama_presentation', 'title' => 'Agege drama', 'activity_date' => now()->subDays(3)->toDateString(), 'attendance_count' => 250, 'status' => 'approved', 'created_by' => $this->users['agege']->id]);
        ActivityReport::create(['org_unit_id' => $this->mokola->id, 'activity_type' => 'workshop', 'title' => 'Mokola workshop', 'activity_date' => now()->subDays(3)->toDateString(), 'attendance_count' => 40, 'status' => 'approved', 'created_by' => $this->users['mokola']->id]);

        // Lagos sees Lagos: its two Assemblies, and only Lagos activity.
        $this->actingAs($this->users['lagos'])->get(route('analytics'))->assertOk()
            ->assertSee('Lagos')->assertSee('Agege')->assertSee('Mushin')->assertDontSee('Mokola')
            ->assertSee('250')->assertSee('Drama presentation')->assertDontSee('Workshop');

        // Not above their District, and not a neighbouring District.
        $this->actingAs($this->users['lagos'])->get(route('analytics', ['scope' => $this->region->id]))->assertForbidden();
        $this->actingAs($this->users['lagos'])->get(route('analytics', ['scope' => $this->ibadan->id]))->assertForbidden();

        // The Region compares its Districts and can open one.
        $this->actingAs($this->users['region'])->get(route('analytics', ['scope' => $this->ibadan->id, 'months' => 3]))->assertOk()->assertSee('Mokola');

        // Assembly Coordinators and members have no analytics permission.
        $this->actingAs($this->users['agege'])->get(route('analytics'))->assertForbidden();

        $csv = $this->actingAs($this->users['region'])->get(route('analytics.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"People reached",290', $csv);
        $this->assertStringContainsString('Lagos,', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'analytics.exported']);
    }

    public function test_share_panel_prepares_messages_for_organisers_only(): void
    {
        $event = Event::create([
            'title' => 'Drama night', 'type' => 'performance', 'org_unit_id' => $this->lagos->id, 'description' => 'An evening of drama.',
            'starts_at' => now()->addWeek(), 'location' => 'District headquarters', 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs($this->users['lagos'])->get(route('events.show', $event))->assertOk()
            ->assertSee('Share on WhatsApp and social media')->assertSee(route('share.card', ['event', $event->slug]));
        $this->actingAs($this->users['mokola'])->get(route('events.show', $event))->assertOk()
            ->assertDontSee('Share on WhatsApp and social media');

        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Lagos rehearsal', 'body' => 'All Lagos Assemblies meet on Saturday.', 'audience_units' => [$this->lagos->id],
        ]);
        $announcement = Announcement::firstOrFail();
        $this->actingAs($this->users['lagos'])->get(route('announcements.show', $announcement))->assertSee('Share on WhatsApp and social media');
        $this->actingAs($this->users['mushin'])->get(route('announcements.show', $announcement))->assertDontSee('Share on WhatsApp and social media');

        // Picture cards exist only for public items.
        $this->get(route('share.card', ['announcement', $announcement->id]))->assertNotFound();
    }

    public function test_integrations_page_is_honest_about_what_is_connected(): void
    {
        config(['mail.default' => 'log', 'godram.push.public_key' => null]);
        $this->actingAs($this->users['national'])->get(route('admin.integrations'))->assertOk()
            ->assertSee('WhatsApp does not allow it')->assertSee('Share and export')->assertSee('Not set up yet')->assertSee('godram:push-keys');
        $this->actingAs($this->users['lagos'])->get(route('admin.integrations'))->assertForbidden();
    }
}
