<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    public function test_district_announcement_reaches_only_that_district(): void
    {
        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Lagos rehearsal on Saturday',
            'body' => 'All Lagos Assemblies meet at the District headquarters.',
            'audience_units' => [$this->lagos->id],
        ])->assertRedirect();

        $announcement = Announcement::firstOrFail();
        $this->assertSame('published', $announcement->status, 'A District notice to its own District goes out straight away.');

        $this->actingAs($this->users['mushin'])->get(route('announcements.index'))->assertSee('Lagos rehearsal on Saturday');
        $this->actingAs($this->users['mokola'])->get(route('announcements.index'))->assertDontSee('Lagos rehearsal on Saturday');
        $this->actingAs($this->users['mokola'])->get(route('announcements.show', $announcement))->assertNotFound();
        auth()->logout();
        $this->get(route('announcements.index'))->assertDontSee('Lagos rehearsal on Saturday');
    }

    public function test_cannot_target_a_unit_outside_your_scope(): void
    {
        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Hello Ibadan', 'body' => 'Text', 'audience_units' => [$this->ibadan->id],
        ])->assertForbidden();
    }

    public function test_public_announcement_needs_approval_before_guests_see_it(): void
    {
        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Christmas production at the National Theatre',
            'body' => 'Everyone is welcome.',
            'audience_public' => 1,
        ])->assertRedirect(route('announcements.manage'));

        $announcement = Announcement::firstOrFail();
        $this->assertSame('submitted', $announcement->status);
        $this->get(route('announcements.index'))->assertDontSee('Christmas production');

        $this->actingAs($this->users['lagos'])->post(route('announcements.decide', $announcement), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->users['national'])->post(route('announcements.decide', $announcement), ['decision' => 'approve'])->assertRedirect();

        auth()->logout();
        $this->get(route('announcements.index'))->assertSee('Christmas production');
        $this->get(route('home'))->assertSee('Christmas production');
    }

    public function test_role_targeted_announcement_reaches_holders_of_that_role(): void
    {
        $this->actingAs($this->users['national'])->post(route('announcements.store'), [
            'title' => 'Coordinators: reports due by the 5th',
            'body' => 'Please submit last month\'s reports.',
            'audience_roles' => ['assembly_coordinator'],
        ])->assertRedirect();

        $this->actingAs($this->users['mokola'])->get(route('announcements.index'))->assertSee('reports due by the 5th');
        $plain = $this->person($this->mokola, 'Plain', 'Member', password: 'secret-pass-1')->user;
        $this->actingAs($plain)->get(route('announcements.index'))->assertDontSee('reports due by the 5th');
    }

    public function test_announcement_needs_an_audience(): void
    {
        $this->actingAs($this->users['lagos'])
            ->post(route('announcements.store'), ['title' => 'No audience', 'body' => 'Text'])
            ->assertSessionHasErrors('audience');
        $this->assertSame(0, Announcement::count());
    }
}
