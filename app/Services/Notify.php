<?php

namespace App\Services;

use App\Models\AchievementRule;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Event;
use App\Models\Exam;
use App\Models\OrgUnit;
use App\Models\SentReminder;
use App\Models\User;
use App\Models\Video;
use App\Notifications\GodramNotice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Decides who hears about what. Actions reach here through the audit log
 * (every important change is already logged there), so a new feature only
 * needs a line in fromAudit() to notify people.
 */
class Notify
{
    /** Audit actions that notify someone. Anything else is ignored without queueing work. */
    public const ROUTED = [
        'announcement.published',
        'report.submitted', 'report.resubmitted', 'report.approved', 'report.returned',
        'event.created', 'event.cancelled',
        'academy.course_published',
        'exam.published', 'exam.results_released',
        'certificate.issued', 'achievement.awarded',
        'video.imported', 'video.publish',
        'member.signup_confirmed', 'role.assigned',
    ];

    public static function routes(string $action): bool
    {
        return in_array($action, self::ROUTED, true);
    }

    // Audiences. Each returns active sign-in accounts.

    public function everyone(): Builder
    {
        return User::query()->where('is_active', true)
            ->whereHas('member', fn ($q) => $q->approved()->where('status', '!=', 'archived'));
    }

    /** People whose Assembly is this unit or anywhere under it. */
    public function within(?OrgUnit $unit): Builder
    {
        if (! $unit || $unit->type === OrgUnit::NATIONAL) {
            return $this->everyone();
        }

        return $this->everyone()->whereHas('member', fn ($q) => $q->placedWithin($unit));
    }

    public function members(iterable $memberIds): Builder
    {
        $ids = collect($memberIds)->filter()->unique()->values()->all();

        return User::query()->where('is_active', true)->whereIn('member_id', $ids ?: [0]);
    }

    /** People holding a role with this permission at exactly this unit. */
    public function holders(string $permission, OrgUnit $unit): Builder
    {
        return User::query()->where('is_active', true)->whereHas('member.activeRoleAssignments', fn ($q) => $q
            ->where('org_unit_id', $unit->id)
            ->whereHas('role.permissions', fn ($q) => $q->where('key', $permission)));
    }

    /** Who reviews a report from this unit: the level directly above, or the next filled post above that. */
    public function reviewersFor(OrgUnit $unit): Builder
    {
        $level = $unit->parent;
        while ($level) {
            $query = $this->holders('reports.approve', $level);
            if ($query->exists()) {
                return $query;
            }
            $level = $level->parent;
        }

        return User::query()->whereRaw('1 = 0');
    }

    public function announcementAudience(Announcement $announcement): Builder
    {
        $targets = $announcement->targets()->get();
        if ($targets->contains('audience', 'public')) {
            return $this->everyone();
        }
        $units = OrgUnit::whereIn('id', $targets->where('audience', 'org_unit')->pluck('org_unit_id'))->get();
        if ($units->contains('type', OrgUnit::NATIONAL)) {
            return $this->everyone();
        }
        $roles = $targets->where('audience', 'role')->pluck('role_key')->filter()->values()->all();

        return $this->everyone()->where(fn ($q) => $q
            ->whereHas('member', fn ($q) => $q->placedWithinPaths($units->pluck('path')->all() ?: ['-']))
            ->orWhereHas('member.activeRoleAssignments.role', fn ($q) => $q->whereIn('key', $roles ?: [''])));
    }

    public function courseAudience(Course $course): Builder
    {
        $targets = $course->targets()->get();
        if ($targets->isEmpty()) {
            return $this->within($course->orgUnit);
        }
        $units = OrgUnit::whereIn('id', $targets->where('audience', 'org_unit')->pluck('org_unit_id'))->get();
        $roles = $targets->where('audience', 'role')->pluck('role_key')->filter()->values()->all();

        return $this->everyone()->where(fn ($q) => $q
            ->whereHas('member', fn ($q) => $q->placedWithinPaths($units->pluck('path')->all() ?: ['-']))
            ->orWhereHas('member.activeRoleAssignments.role', fn ($q) => $q->whereIn('key', $roles ?: [''])));
    }

    public function examAudience(Exam $exam): Builder
    {
        if ($exam->course_id) {
            return $this->members(Enrolment::where('course_id', $exam->course_id)->where('status', '!=', 'withdrawn')->pluck('member_id'));
        }

        return $this->within($exam->orgUnit);
    }

    public function eventAttendees(Event $event): Builder
    {
        return $this->members($event->registrations()->where('status', '!=', 'cancelled')->pluck('member_id'));
    }

    /** Sends the notice to everyone in the audience except the person who caused it. Returns how many received it. */
    public function send(Builder $users, GodramNotice $notice, ?int $except = null): int
    {
        $count = 0;
        $users->when($except, fn ($q) => $q->whereKeyNot($except))
            ->with(['member', 'notificationPreferences'])
            ->chunkById(200, function ($chunk) use ($notice, &$count) {
                Notification::send($chunk, $notice);
                $count += $chunk->count();
            });

        return $count;
    }

    /** Sends something once only, keyed so scheduled runs and retries never repeat it. */
    public function once(string $key, Builder $users, GodramNotice $notice, ?int $except = null): int
    {
        $marker = SentReminder::firstOrCreate(['key' => $key], ['created_at' => now()]);
        if (! $marker->wasRecentlyCreated) {
            return 0;
        }
        $count = $this->send($users, $notice, $except);
        $marker->forceFill(['recipients' => $count])->save();

        return $count;
    }

    public function fromAudit(AuditLog $entry): int
    {
        $class = $entry->subject_type ? 'App\\Models\\'.$entry->subject_type : null;
        $subject = $class && class_exists($class) ? $class::find($entry->subject_id) : null;
        if (! $subject) {
            return 0;
        }
        $actor = $entry->user_id;

        return match ($entry->action) {
            'announcement.published' => $this->announcement($subject),
            'report.submitted', 'report.resubmitted' => $this->send($this->reviewersFor($subject->orgUnit), new GodramNotice(
                'reports', 'Report waiting for your review',
                $subject->orgUnit->fullName().': '.$subject->displayTitle().'.',
                route('reports.show', $subject),
            ), $actor),
            'report.approved' => $this->send(User::whereKey($subject->created_by), new GodramNotice(
                'reports', 'Your report was approved',
                $subject->displayTitle().($entry->data['note'] ?? null ? '. Note from your reviewer: '.$entry->data['note'] : '.'),
                route('reports.show', $subject),
            )),
            'report.returned' => $this->send(User::whereKey($subject->created_by), new GodramNotice(
                'reports', 'Your report needs correcting',
                $subject->displayTitle().'. '.($entry->data['note'] ?? 'Open it to see what to change.'),
                route('reports.edit', $subject),
            )),
            'event.created' => $this->event($subject, $actor),
            'event.cancelled' => $this->send($this->eventAttendees($subject), new GodramNotice(
                'events', 'Cancelled: '.$subject->title,
                'This event on '.$subject->starts_at->format('l j F').' will not hold. '.($subject->cancel_reason ?? ''),
                route('events.show', $subject), true,
            ), $actor),
            'academy.course_published' => $this->once('course:'.$subject->id, $this->courseAudience($subject), new GodramNotice(
                'training', 'New training: '.$subject->title,
                trim(($subject->summary ? Str::limit($subject->summary, 140) : 'Training from '.$subject->orgUnit?->fullName().'.').($subject->enrol_by ? ' Enrol by '.$subject->enrol_by->format('j F').'.' : '')),
                route('academy.show', $subject),
            ), $actor),
            'exam.published' => $this->once('exam:'.$subject->id, $this->examAudience($subject), new GodramNotice(
                'exams', 'Examination: '.$subject->title,
                $subject->windowLabel().'. '.$subject->duration_minutes.' minutes.',
                route('exams.show', $subject),
            ), $actor),
            'exam.results_released' => $this->send($this->members($subject->attempts()->pluck('member_id')), new GodramNotice(
                'exams', 'Results are out: '.$subject->title,
                'Your result for '.$subject->title.' is ready to view.',
                route('exams.show', $subject), true,
            )),
            'certificate.issued' => $this->send($this->members([$subject->member_id]), new GodramNotice(
                'certificates', 'Certificate issued',
                $subject->title.($subject->programme ? ', '.$subject->programme : '').'. Number '.$subject->number.'.',
                route('certificates.mine'),
            )),
            'achievement.awarded' => $this->send($this->members([$subject->id]), new GodramNotice(
                'achievements', 'Well done: '.(AchievementRule::find($entry->data['rule'] ?? 0)?->name ?? 'new achievement'),
                'You reached a GODRAM milestone. See it on your profile.',
                route('profile'),
            )),
            'video.imported', 'video.publish' => $this->video($subject, $actor),
            'member.signup_confirmed' => $this->send($this->members([$subject->id]), new GodramNotice(
                'account', 'Welcome to GODRAM CONNECT',
                'Your membership of '.($subject->assembly()?->fullName() ?? 'GODRAM').' is confirmed. Complete your profile so your Coordinators know your gifts.',
                route('profile'),
            )),
            'role.assigned' => $this->send($this->members([$subject->id]), new GodramNotice(
                'leadership', 'Your new appointment',
                'You have been appointed '.Str::after((string) $entry->summary, ' appointed ').'. Your new tools are on your dashboard.',
                route('dashboard'),
            )),
            default => 0,
        };
    }

    /** Announcements go out when they go live; ones scheduled for later are sent by godram:reminders. */
    public function announcement(Announcement $a): int
    {
        if ($a->status !== 'published' || ($a->published_at && $a->published_at->isFuture())) {
            return 0;
        }

        return $this->once('announcement:'.$a->id, $this->announcementAudience($a), new GodramNotice(
            $a->is_mandatory ? 'leadership' : 'announcements',
            $a->title,
            Str::limit(trim(strip_tags($a->body)), 160),
            route('announcements.show', $a),
            $a->is_mandatory,
        ), $a->author_id);
    }

    protected function event(Event $event, ?int $actor): int
    {
        if ($event->status !== 'published' || $event->isPast()) {
            return 0;
        }
        $where = $event->is_online ? 'Online' : $event->location;

        return $this->once('event:'.$event->id.':new', $this->within($event->orgUnit), new GodramNotice(
            'events', 'New event: '.$event->title,
            $event->starts_at->format('l j F, g:ia').($where ? ' · '.$where : '').'.',
            route('events.show', $event),
        ), $actor);
    }

    protected function video(Video $video, ?int $actor): int
    {
        // An import can bring in older uploads; only new ones are news.
        if ($video->status !== 'published' || ($video->published_at && $video->published_at->lt(now()->subDays(7)))) {
            return 0;
        }

        return $this->once('video:'.$video->id, $this->everyone(), new GodramNotice(
            'videos', 'New on GODRAM TV', $video->title, route('watch.show', $video),
        ), $actor);
    }
}
