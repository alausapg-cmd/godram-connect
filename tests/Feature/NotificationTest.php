<?php

namespace Tests\Feature;

use App\Models\ActivityReport;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PushSubscription;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\GodramNotice;
use App\Services\Reminders;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    protected function titles($user): array
    {
        return $user->fresh()->notifications()->get()->pluck('data.title')->all();
    }

    protected function submitAgegeReport(): ActivityReport
    {
        $this->actingAs($this->users['agege'])->post(route('reports.start'), ['org_unit_id' => $this->agege->id]);
        $report = ActivityReport::latest('id')->firstOrFail();
        $this->actingAs($this->users['agege'])->put(route('reports.update', $report), [
            'action' => 'submit', 'activity_type' => 'evangelistic_performance', 'title' => 'Market square outreach',
            'activity_date' => now()->subDays(2)->toDateString(), 'location' => 'Agege market',
            'description' => 'Drama and altar call.', 'performers_count' => 12, 'attendance_count' => 300, 'souls_won' => 14,
        ]);

        return $report->fresh();
    }

    public function test_report_review_notifies_the_district_then_the_author(): void
    {
        $report = $this->submitAgegeReport();

        $this->assertSame(['Report waiting for your review'], $this->titles($this->users['lagos']));
        $this->assertSame([], $this->titles($this->users['ibadan']), 'Only the level directly above is asked to review.');
        $this->assertSame([], $this->titles($this->users['region']));

        $this->actingAs($this->users['lagos'])->post(route('reports.review', $report), ['decision' => 'reject', 'note' => 'Add the photographs, please.']);
        $notice = $this->users['agege']->fresh()->notifications()->first();
        $this->assertSame('Your report needs correcting', $notice->data['title']);
        $this->assertStringContainsString('Add the photographs', $notice->data['body']);
        $this->assertSame(route('reports.edit', $report), $notice->data['url']);
    }

    public function test_announcement_reaches_its_audience_only(): void
    {
        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Lagos rehearsal on Saturday', 'body' => 'All Lagos Assemblies meet at the District headquarters.',
            'audience_units' => [$this->lagos->id],
        ]);

        $this->assertContains('Lagos rehearsal on Saturday', $this->titles($this->users['mushin']));
        $this->assertContains('Lagos rehearsal on Saturday', $this->titles($this->users['agege']));
        $this->assertNotContains('Lagos rehearsal on Saturday', $this->titles($this->users['mokola']));
        $this->assertNotContains('Lagos rehearsal on Saturday', $this->titles($this->users['lagos']), 'The writer is not told about their own announcement.');
    }

    public function test_email_follows_choices_but_mandatory_messages_always_go(): void
    {
        Notification::fake();
        $mushin = $this->users['mushin'];

        // Announcements are not emailed by default.
        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Ordinary notice', 'body' => 'Text.', 'audience_units' => [$this->lagos->id],
        ]);
        Notification::assertSentTo($mushin, GodramNotice::class, fn ($n, $channels) => $n->title === 'Ordinary notice' && $channels === ['database']);

        // Turning email on for announcements is remembered.
        $this->actingAs($mushin)->put(route('notifications.settings.update'), ['email' => ['announcements' => 1, 'reports' => 1], 'push' => []])->assertRedirect();
        $this->assertTrue($mushin->fresh()->wantsNotice('announcements', 'email'));
        $this->assertFalse($mushin->fresh()->wantsNotice('events', 'push'));

        // Mandatory leadership messages ignore the choices.
        $this->actingAs($mushin)->put(route('notifications.settings.update'), ['email' => [], 'push' => []]);
        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Mandatory meeting', 'body' => 'Every Coordinator attends.', 'audience_units' => [$this->lagos->id], 'is_mandatory' => 1,
        ]);
        Notification::assertSentTo($mushin, GodramNotice::class, fn ($n, $channels) => $n->title === 'Mandatory meeting' && $n->category === 'leadership' && in_array('mail', $channels));
    }

    public function test_push_goes_only_to_people_with_a_device_when_keys_are_set(): void
    {
        Notification::fake();
        config(['godram.push.public_key' => 'BPublicKeyForTests', 'godram.push.private_key' => 'PrivateKeyForTests']);

        $this->actingAs($this->users['mushin'])->postJson(route('notifications.devices.store'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => ['p256dh' => 'BKeyKeyKey', 'auth' => 'authauth'],
            'encoding' => 'aes128gcm',
        ], ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0'])->assertOk();
        $this->assertSame('Android phone · Chrome', PushSubscription::first()->device);

        $this->actingAs($this->users['lagos'])->post(route('announcements.store'), [
            'title' => 'Rehearsal moved', 'body' => 'Now at 4pm.', 'audience_units' => [$this->lagos->id],
        ]);
        Notification::assertSentTo($this->users['mushin'], GodramNotice::class, fn ($n, $channels) => in_array(WebPushChannel::class, $channels));
        Notification::assertSentTo($this->users['agege'], GodramNotice::class, fn ($n, $channels) => ! in_array(WebPushChannel::class, $channels));

        $this->actingAs($this->users['mushin'])->deleteJson(route('notifications.devices.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_bell_count_and_opening_a_notice(): void
    {
        $mushin = $this->users['mushin'];
        $mushin->notify(new GodramNotice('events', 'New event: Drama night', 'Friday 6pm.', route('events')));
        $mushin->notify(new GodramNotice('events', 'Sneaky link', 'Text.', 'https://example.com/elsewhere'));

        $this->actingAs($mushin)->get(route('dashboard'))->assertSee('Notifications, 2 unread');
        $this->actingAs($mushin)->get(route('notifications.index'))->assertOk()->assertSee('New event: Drama night')->assertSee('Mark all read');

        $first = $mushin->notifications()->where('data->title', 'New event: Drama night')->first();
        $this->actingAs($mushin)->get(route('notifications.open', $first->id))->assertRedirect(route('events'));
        $this->assertNotNull($first->fresh()->read_at);

        $other = $mushin->notifications()->where('data->title', 'Sneaky link')->first();
        $this->actingAs($mushin)->get(route('notifications.open', $other->id))->assertRedirect(route('notifications.index'));

        // Nobody can open someone else's notice.
        $this->actingAs($this->users['agege'])->get(route('notifications.open', $first->id))->assertNotFound();

        $this->actingAs($mushin)->get(route('notifications.settings'))->assertOk()->assertSee('Leadership messages')->assertSee('Always on');
    }

    public function test_reminders_go_once_to_registered_members_and_quiet_assemblies(): void
    {
        $event = Event::create([
            'title' => 'Drama night', 'type' => 'performance', 'org_unit_id' => $this->lagos->id, 'description' => 'An evening of drama.',
            'starts_at' => now()->addHours(20), 'location' => 'District headquarters', 'visibility' => 'public', 'status' => 'published', 'published_at' => now(),
        ]);
        EventRegistration::create(['event_id' => $event->id, 'member_id' => $this->users['mushin']->member_id, 'name' => 'Funmi Mushin', 'status' => 'registered']);

        $this->travelTo(now()->setDay(25)->setTime(9, 0));
        $event->update(['starts_at' => now()->addHours(20)]);
        ActivityReport::create(['org_unit_id' => $this->mokola->id, 'title' => 'Mokola outreach', 'activity_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->users['mokola']->id]);

        app(Reminders::class)->run();
        app(Reminders::class)->run();

        $this->assertSame(1, collect($this->titles($this->users['mushin']))->filter(fn ($t) => str_contains($t, 'Drama night'))->count(), 'Sent once however often cron runs.');
        $this->assertNotContains('Tomorrow: Drama night', $this->titles($this->users['agege']), 'Only people who registered are reminded.');

        $this->assertContains('Monthly report reminder', $this->titles($this->users['agege']));
        $this->assertContains('Monthly report reminder', $this->titles($this->users['mushin']));
        $this->assertNotContains('Monthly report reminder', $this->titles($this->users['mokola']), 'Mokola has started a report this month.');
        $this->assertNotContains('Monthly report reminder', $this->titles($this->users['lagos']));
    }
}
