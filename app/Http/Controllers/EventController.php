<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\OrgUnit;
use App\Models\Production;
use App\Services\Access;
use App\Services\AuditLogger;
use App\Services\ImageStore;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function __construct(protected Access $access, protected AuditLogger $audit) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->input('type');
        $base = fn () => Event::visibleTo($user)->with('orgUnit.parent')
            ->when($type && isset(Event::TYPES[$type]), fn ($q) => $q->where('type', $type));

        $upcoming = $base()->upcoming()->limit(40)->get();
        $live = $upcoming->filter->isLive()->values();

        return view('events.index', [
            'live' => $live,
            'upcoming' => $upcoming->reject->isLive()->values(),
            'broadcasts' => $base()->past()->streamed()->limit(6)->get(),
            'past' => $base()->past()->limit(9)->get(),
            'type' => $type,
            'canCreate' => $this->access->can($user, 'events.create'),
            'mine' => $user?->member ? EventRegistration::where('member_id', $user->member_id)->where('status', 'registered')
                ->whereHas('event', fn ($q) => $q->upcoming())->with('event')->get() : collect(),
        ]);
    }

    public function show(Request $request, Event $event)
    {
        $user = $request->user();
        abort_unless($event->isVisibleTo($user), 404);
        $event->load(['orgUnit.parent', 'people.member', 'production', 'videos']);

        return view('events.show', [
            'event' => $event,
            'canManage' => $event->canBeManagedBy($user),
            'registration' => $user?->member_id ? $event->registrations()->where('member_id', $user->member_id)->where('status', '!=', 'cancelled')->first() : null,
            'registeredCount' => $event->activeRegistrations()->count(),
            'related' => Event::visibleTo($user)->upcoming()->whereKeyNot($event->id)
                ->where(fn ($q) => $q->where('type', $event->type)->orWhere('org_unit_id', $event->org_unit_id))->limit(3)->get(),
        ]);
    }

    public function calendar(Request $request, Event $event)
    {
        abort_unless($event->isVisibleTo($request->user()), 404);
        $stamp = fn ($t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $escape = fn (string $s) => addcslashes(str_replace(["\r\n", "\n"], '\\n', $s), ',;');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//GODRAM//GODRAM CONNECT//EN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$stamp(now()),
            'DTSTART:'.$stamp($event->starts_at),
            'DTEND:'.$stamp($event->endsAt()),
            'SUMMARY:'.$escape($event->title),
            'DESCRIPTION:'.$escape(Str::limit($event->description, 800)."\n\n".route('events.show', $event)),
            'LOCATION:'.$escape($event->is_online ? ($event->stream_url ?? 'Online') : trim($event->location.', '.$event->address, ', ')),
            'URL:'.route('events.show', $event),
            $event->isCancelled() ? 'STATUS:CANCELLED' : 'STATUS:CONFIRMED',
            'BEGIN:VALARM', 'TRIGGER:-PT1H', 'ACTION:DISPLAY', 'DESCRIPTION:'.$escape($event->title), 'END:VALARM',
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->access->can($request->user(), 'events.create'), 403);

        return $this->form($request, new Event([
            'type' => 'performance',
            'visibility' => 'public',
            'starts_at' => now()->addWeek()->setTime(10, 0),
        ]));
    }

    public function store(Request $request, ImageStore $images)
    {
        abort_unless($this->access->can($request->user(), 'events.create'), 403);
        $data = $this->validated($request);

        $event = DB::transaction(function () use ($request, $data, $images) {
            $event = Event::create($data + ['created_by' => $request->user()->id, 'status' => 'published', 'published_at' => now()]);
            $this->syncPeople($event, $request->input('people', []));
            if ($request->hasFile('cover')) {
                $event->forceFill(['cover_path' => $images->storeImage($request->file('cover'), 'events', 1600)])->save();
            }

            return $event;
        });
        $this->audit->log('event.created', $event, 'Event published: '.$event->title, [], $event->orgUnit);

        return redirect()->route('events.show', $event)->with('status', 'Your event is on the calendar.');
    }

    public function edit(Request $request, Event $event)
    {
        abort_unless($event->canBeManagedBy($request->user()), 403);

        return $this->form($request, $event->load('people'));
    }

    public function update(Request $request, Event $event, ImageStore $images)
    {
        abort_unless($event->canBeManagedBy($request->user()), 403);
        $data = $this->validated($request, $event);

        DB::transaction(function () use ($request, $event, $data, $images) {
            $event->update($data);
            $this->syncPeople($event, $request->input('people', []));
            if ($request->hasFile('cover')) {
                $event->forceFill(['cover_path' => $images->storeImage($request->file('cover'), 'events', 1600)])->save();
            }
        });
        $this->audit->log('event.updated', $event, 'Event updated: '.$event->title, [], $event->orgUnit);

        return redirect()->route('events.show', $event)->with('status', 'Event updated.');
    }

    public function cancel(Request $request, Event $event)
    {
        abort_unless($event->canBeManagedBy($request->user()), 403);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:250']], ['cancel_reason.required' => 'Tell people why the event is cancelled.']);
        $event->forceFill(['status' => 'cancelled', 'cancel_reason' => $data['cancel_reason'], 'registration_open' => false])->save();
        $this->audit->log('event.cancelled', $event, 'Event cancelled: '.$event->title, $data, $event->orgUnit);

        return back()->with('status', 'The event is marked as cancelled. It stays on the calendar so people see the notice.');
    }

    public function register(Request $request, Event $event)
    {
        $user = $request->user();
        abort_unless($event->isVisibleTo($user), 404);
        if (! $event->acceptsRegistrations()) {
            return back()->withErrors(['registration' => 'Registration for this event is closed.']);
        }

        if ($user?->member) {
            $member = $user->member;
            $existing = $event->registrations()->where('member_id', $member->id)->first();
            if ($existing && $existing->status !== 'cancelled') {
                return back()->with('status', 'You are already registered. Your reference is '.$existing->reference.'.');
            }
            $registration = $existing
                ? tap($existing)->update(['status' => 'registered'])
                : $event->registrations()->create(['member_id' => $member->id, 'name' => $member->full_name, 'phone' => $member->phone, 'email' => $member->email]);
        } else {
            abort_unless($event->visibility === 'public', 404);
            $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['required', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:120'],
            ]);
            if ($event->registrations()->where('phone', $data['phone'])->where('status', '!=', 'cancelled')->exists()) {
                throw ValidationException::withMessages(['phone' => 'This phone number is already registered for the event.']);
            }
            $registration = $event->registrations()->create($data);
        }

        return back()->with('status', 'You are registered. Your reference is '.$registration->reference.'. Add the event to your calendar so you do not miss it.');
    }

    public function unregister(Request $request, Event $event)
    {
        $registration = $event->registrations()->where('member_id', $request->user()->member_id)->where('status', 'registered')->firstOrFail();
        $registration->update(['status' => 'cancelled']);

        return back()->with('status', 'Your place has been released.');
    }

    public function registrations(Request $request, Event $event)
    {
        abort_unless($event->canBeManagedBy($request->user()), 403);
        $registrations = $event->registrations()->with('member.currentPlacement.orgUnit')->orderBy('status')->orderBy('name')->get();

        if ($request->input('format') === 'csv') {
            $this->audit->log('event.registrations_exported', $event, 'Registrations exported: '.$event->title, [], $event->orgUnit);

            return response()->streamDownload(function () use ($registrations) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Reference', 'Name', 'Member ID', 'Assembly', 'Phone', 'Email', 'Status', 'Registered']);
                foreach ($registrations as $r) {
                    fputcsv($out, [$r->reference, $r->name, $r->member?->member_no, $r->member?->currentPlacement?->orgUnit?->name, $r->phone, $r->email, $r->status, $r->created_at->format('Y-m-d H:i')]);
                }
                fclose($out);
            }, $event->slug.'-registrations.csv', ['Content-Type' => 'text/csv']);
        }

        return view('events.registrations', compact('event', 'registrations'));
    }

    public function markAttended(Request $request, Event $event, EventRegistration $registration)
    {
        abort_unless($event->canBeManagedBy($request->user()) && $registration->event_id === $event->id, 403);
        $attended = ! $registration->attended_at;
        $registration->update(['attended_at' => $attended ? now() : null, 'status' => $attended ? 'attended' : 'registered']);

        return back();
    }

    protected function form(Request $request, Event $event)
    {
        $user = $request->user();
        $units = $this->access->units($user, 'events.create');
        if ($this->access->can($user, 'media.manage') && ($root = OrgUnit::root()) && ! $units->contains('id', $root->id)) {
            $units->prepend($root);
        }

        return view('events.form', [
            'event' => $event,
            'units' => $units,
            'productions' => Production::published()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    protected function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'org_unit_id' => ['required', 'exists:org_units,id'],
            'description' => ['required', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'required_unless:is_online,1', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:200'],
            'is_online' => ['nullable', 'boolean'],
            'stream_url' => ['nullable', 'url', 'max:255'],
            'stream_platform' => ['nullable', 'required_with:stream_url', Rule::in(array_keys(Event::PLATFORMS))],
            'replay_url' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'registration_open' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'production_id' => ['nullable', 'exists:productions,id'],
            'cover' => ['nullable', 'image', 'max:'.config('godram.uploads.image_max_kb')],
            'people' => ['array', 'max:30'],
            'people.*.name' => ['nullable', 'string', 'max:120'],
            'people.*.role' => ['nullable', Rule::in(array_keys(Event::ROLES))],
        ], [
            'location.required_unless' => 'Say where the event takes place, or tick "Online only".',
            'stream_platform.required_with' => 'Choose where the stream is hosted.',
        ]);

        $unit = OrgUnit::findOrFail($data['org_unit_id']);
        abort_unless($this->access->can($request->user(), 'events.create', $unit) || $this->access->can($request->user(), 'media.manage'), 403);

        $replay = null;
        if (filled($data['replay_url'] ?? null)) {
            $replay = \App\Models\Video::youtubeIdFrom($data['replay_url']);
            if (! $replay) {
                throw ValidationException::withMessages(['replay_url' => 'Paste a YouTube link for the replay.']);
            }
        }

        return collect($data)->except(['cover', 'people', 'replay_url'])->merge([
            'is_online' => $request->boolean('is_online'),
            'registration_open' => $request->boolean('registration_open'),
            'replay_youtube_id' => $replay,
        ])->all();
    }

    protected function syncPeople(Event $event, array $people): void
    {
        $event->people()->delete();
        foreach (array_values(array_filter($people, fn ($p) => filled($p['name'] ?? null))) as $i => $person) {
            $event->people()->create([
                'name' => trim($person['name']),
                'role' => $person['role'] ?? 'performer',
                'sort' => $i,
            ]);
        }
    }
}
