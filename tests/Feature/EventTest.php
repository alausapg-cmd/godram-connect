<?php

namespace Tests\Feature;

use App\Models\Event;
use Tests\Concerns\BuildsMinistry;
use Tests\TestCase;

class EventTest extends TestCase
{
    use BuildsMinistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMinistry();
    }

    protected function event(array $attributes = []): Event
    {
        return Event::create($attributes + [
            'title' => 'Easter drama night',
            'type' => 'performance',
            'org_unit_id' => $this->lagos->id,
            'description' => 'An evening of drama at the District headquarters.',
            'starts_at' => now()->addWeek(),
            'location' => 'District headquarters',
            'visibility' => 'public',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_district_coordinator_creates_events_only_inside_their_district(): void
    {
        $form = [
            'title' => 'Lagos combined rehearsal',
            'type' => 'workshop',
            'description' => 'Every Assembly in Lagos brings its cast.',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i'),
            'location' => 'Mushin',
            'visibility' => 'public',
        ];

        $this->actingAs($this->users['lagos'])->post(route('events.store'), $form + ['org_unit_id' => $this->lagos->id])->assertRedirect();
        $this->assertDatabaseHas('events', ['title' => 'Lagos combined rehearsal', 'org_unit_id' => $this->lagos->id]);

        $this->actingAs($this->users['lagos'])->post(route('events.store'), $form + ['org_unit_id' => $this->ibadan->id])->assertForbidden();

        $event = Event::firstWhere('title', 'Lagos combined rehearsal');
        $this->actingAs($this->users['agege'])->get(route('events.edit', $event))->assertForbidden();
        $this->actingAs($this->users['region'])->get(route('events.edit', $event))->assertOk();
    }

    public function test_guest_registration_and_capacity(): void
    {
        $event = $this->event(['registration_open' => true, 'capacity' => 2]);

        $this->post(route('events.register', $event), ['name' => 'Ada Obi', 'phone' => '0803 111 2222'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id, 'phone' => '+2348031112222']);
        $this->assertMatchesRegularExpression('/^EVT-[A-Z0-9]{6}$/', $event->registrations()->first()->reference);

        $this->post(route('events.register', $event), ['name' => 'Ada again', 'phone' => '+2348031112222'])->assertSessionHasErrors('phone');

        $member = $this->users['mushin'];
        $this->actingAs($member)->post(route('events.register', $event))->assertSessionHasNoErrors();
        $this->assertSame(0, $event->fresh()->placesLeft());

        auth()->logout();
        $this->post(route('events.register', $event), ['name' => 'Late comer', 'phone' => '08039998888'])->assertSessionHasErrors('registration');

        $this->actingAs($member)->delete(route('events.unregister', $event))->assertRedirect();
        $this->assertSame(1, $event->fresh()->placesLeft(), 'Releasing a place frees it for someone else.');
    }

    public function test_members_only_events_are_hidden_from_guests(): void
    {
        $event = $this->event(['title' => 'Coordinators retreat', 'visibility' => 'members']);

        $this->get(route('events'))->assertDontSee('Coordinators retreat');
        $this->get(route('events.show', $event))->assertNotFound();
        $this->actingAs($this->users['mokola'])->get(route('events.show', $event))->assertOk()->assertSee('Coordinators retreat');
    }

    public function test_calendar_file(): void
    {
        $event = $this->event(['title' => 'Drama, praise; and prayer']);

        $response = $this->get(route('events.calendar', $event))->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('SUMMARY:Drama\, praise\; and prayer', $body);
        $this->assertStringContainsString('DTSTART:'.$event->starts_at->copy()->utc()->format('Ymd\THis\Z'), $body);
    }

    public function test_cancelled_event_stays_visible_and_closes_registration(): void
    {
        $event = $this->event(['registration_open' => true]);

        $this->actingAs($this->users['lagos'])->post(route('events.cancel', $event), ['cancel_reason' => 'The hall is unavailable.'])->assertRedirect();
        $event->refresh();
        $this->assertSame('cancelled', $event->status);
        $this->assertFalse($event->acceptsRegistrations());

        auth()->logout();
        $this->get(route('events.show', $event))->assertOk()->assertSee('The hall is unavailable.');
    }
}
