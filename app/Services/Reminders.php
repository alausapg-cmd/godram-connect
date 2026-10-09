<?php

namespace App\Services;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\CourseSession;
use App\Models\Enrolment;
use App\Models\Event;
use App\Models\Exam;
use App\Models\OrgUnit;
use App\Notifications\GodramNotice;

/**
 * Time-based notices, run by cron every 15 minutes (godram:reminders).
 * Each reminder has a key, so running more often never sends one twice.
 */
class Reminders
{
    public function __construct(protected Notify $notify) {}

    /** @return array<string, int> recipients per kind of reminder */
    public function run(): array
    {
        return [
            'announcements' => $this->scheduledAnnouncements(),
            'events' => $this->events(),
            'live' => $this->live(),
            'sessions' => $this->sessions(),
            'assignments' => $this->assignments(),
            'exams' => $this->exams(),
            'reports' => $this->monthlyReports(),
        ];
    }

    /** Announcements written earlier with a later publishing time go out once that time arrives. */
    protected function scheduledAnnouncements(): int
    {
        return Announcement::live()->where('published_at', '>=', now()->subDay())->get()
            ->sum(fn (Announcement $a) => $this->notify->announcement($a));
    }

    /** A day before, to people who registered. */
    protected function events(): int
    {
        return $this->upcomingEvents(now()->addHours(2), now()->addDay())->sum(fn (Event $e) => $this->notify->once(
            'event:'.$e->id.':day', $this->notify->eventAttendees($e),
            new GodramNotice('events', ($e->starts_at->isTomorrow() ? 'Tomorrow: ' : 'Coming up: ').$e->title,
                $e->starts_at->format('l j F, g:ia').' · '.($e->is_online ? 'Online' : $e->location).'. Your place is booked.',
                route('events.show', $e)),
        ));
    }

    /** Within the hour: streamed events to everyone in the organising area, others to people who registered. */
    protected function live(): int
    {
        return $this->upcomingEvents(now(), now()->addHour())->sum(fn (Event $e) => $e->stream_url
            ? $this->notify->once('event:'.$e->id.':live', $this->notify->within($e->orgUnit), new GodramNotice(
                'live', 'Live soon: '.$e->title, 'Starts at '.$e->starts_at->format('g:ia').'. Watch it live on GODRAM CONNECT.', route('events.show', $e)))
            : $this->notify->once('event:'.$e->id.':hour', $this->notify->eventAttendees($e), new GodramNotice(
                'events', 'Starting soon: '.$e->title, 'Starts at '.$e->starts_at->format('g:ia').($e->location ? ' at '.$e->location : '').'.', route('events.show', $e))));
    }

    protected function upcomingEvents($from, $to)
    {
        return Event::where('status', 'published')->whereBetween('starts_at', [$from, $to])->with('orgUnit')->get();
    }

    /** Live Academy sessions within the hour, to everyone enrolled. */
    protected function sessions(): int
    {
        return CourseSession::whereBetween('live_at', [now(), now()->addHour()])
            ->whereHas('course', fn ($q) => $q->where('status', 'published'))->with('course')->get()
            ->sum(fn (CourseSession $s) => $this->notify->once('session:'.$s->id.':hour',
                $this->notify->members(Enrolment::where('course_id', $s->course_id)->where('status', '!=', 'withdrawn')->pluck('member_id')),
                new GodramNotice('training', 'Live class soon: '.$s->title,
                    $s->course->title.'. Starts at '.$s->live_at->format('g:ia').'.',
                    route('academy.room', ['course' => $s->course, 'session' => $s]))));
    }

    /** Assignments due within a day, to enrolled members who have not handed in. */
    protected function assignments(): int
    {
        return Assignment::whereBetween('due_at', [now(), now()->addDay()])
            ->whereHas('course', fn ($q) => $q->where('status', 'published'))->with('course')->get()
            ->sum(function (Assignment $a) {
                $done = $a->submissions()->pluck('member_id');
                $waiting = Enrolment::where('course_id', $a->course_id)->where('status', '!=', 'withdrawn')->whereNotIn('member_id', $done)->pluck('member_id');

                return $this->notify->once('assignment:'.$a->id.':due', $this->notify->members($waiting), new GodramNotice(
                    'training', 'Assignment due: '.$a->title,
                    'Hand it in by '.$a->due_at->format('l j F, g:ia').'.',
                    route('academy.assignment', ['course' => $a->course, 'assignment' => $a])));
            });
    }

    /** Examinations opening within a day, and a last call before one closes for those who have not sat it. */
    protected function exams(): int
    {
        $opening = Exam::published()->whereBetween('opens_at', [now()->addMinutes(15), now()->addDay()])->get()
            ->sum(fn (Exam $e) => $this->notify->once('exam:'.$e->id.':opens', $this->notify->examAudience($e), new GodramNotice(
                'exams', 'Examination opens soon: '.$e->title, $e->windowLabel().'.', route('exams.show', $e))));

        $closing = Exam::published()->whereBetween('closes_at', [now()->addHours(2), now()->addDay()])->get()
            ->sum(function (Exam $e) {
                $sat = $e->attempts()->pluck('member_id');

                return $this->notify->once('exam:'.$e->id.':closing', $this->notify->examAudience($e)->whereNotIn('member_id', $sat), new GodramNotice(
                    'exams', 'Last chance: '.$e->title, 'This examination closes '.$e->closes_at->format('l j F \a\t g:ia').'.', route('exams.show', $e)));
            });

        return $opening + $closing;
    }

    /** On the reminder day, Assembly Coordinators with no report this month get a nudge. */
    protected function monthlyReports(): int
    {
        if (now()->day !== (int) config('godram.notify.report_reminder_day')) {
            return 0;
        }
        $month = now()->format('Y-m');
        $reported = ActivityReport::where(fn ($q) => $q
            ->whereBetween('activity_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->orWhere('created_at', '>=', now()->startOfMonth()))->pluck('org_unit_id')->unique();

        return OrgUnit::ofType(OrgUnit::ASSEMBLY)->where('is_active', true)->whereNotIn('id', $reported)->get()
            ->sum(fn (OrgUnit $unit) => $this->notify->once('report:'.$month.':'.$unit->id, $this->notify->holders('reports.create', $unit), new GodramNotice(
                'reports', 'Monthly report reminder',
                'There is no activity report from '.$unit->name.' for '.now()->format('F').' yet. It takes a few minutes on your phone.',
                route('reports.index'))));
    }
}
