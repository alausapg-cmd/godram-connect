<?php

namespace App\Services;

use App\Models\ActivityReport;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrolment;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ExamAttempt;
use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\OrgUnit;
use App\Models\SessionAttendance;
use App\Models\Story;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Figures for one part of the tree over a period. Everything is counted from
 * the records themselves (no tracking scripts), so only what GODRAM CONNECT
 * actually knows is shown. Video views live on YouTube and are not guessed.
 */
class Analytics
{
    protected OrgUnit $scope;

    protected Carbon $from;

    protected int $months;

    public function for(OrgUnit $scope, int $months = 12): array
    {
        $this->scope = $scope;
        $this->months = $months;
        $this->from = now()->startOfMonth()->subMonths($months - 1);

        return [
            'scope' => $scope,
            'months' => $months,
            'from' => $this->from,
            'membership' => $this->membership(),
            'reporting' => $this->reporting(),
            'training' => $this->training(),
            'cbt' => $this->cbt(),
            'events' => $this->events(),
            'recognition' => $this->recognition(),
            'media' => $this->media(),
            'children' => $this->children(),
        ];
    }

    protected function members(?OrgUnit $unit = null): Builder
    {
        return Member::approved()->where('status', '!=', 'archived')->placedWithin($unit ?? $this->scope);
    }

    protected function memberIds(): Builder
    {
        return $this->members()->select('members.id');
    }

    protected function reports(?OrgUnit $unit = null): Builder
    {
        return ActivityReport::withinPaths([($unit ?? $this->scope)->path]);
    }

    protected function inScope(Builder $query, string $relation = 'orgUnit'): Builder
    {
        return $query->whereHas($relation, fn ($q) => $q->where('path', 'like', $this->scope->path.'%'));
    }

    /** One value per month of the period, oldest first. */
    protected function monthly(callable $count): array
    {
        $series = [];
        for ($i = 0; $i < $this->months; $i++) {
            $start = $this->from->copy()->addMonths($i);
            $series[] = ['label' => $start->format('M'), 'title' => $start->format('F Y'), 'value' => $count($start, $start->copy()->endOfMonth())];
        }

        return $series;
    }

    protected function rate(int|float $part, int|float $whole): ?int
    {
        return $whole > 0 ? (int) round(100 * $part / $whole) : null;
    }

    protected function membership(): array
    {
        $total = $this->members()->count();
        $before = $this->members()->where('joined_on', '<', $this->from->toDateString())->count();

        return [
            'total' => $total,
            'active' => $this->members()->where('status', 'active')->count(),
            'new' => $total - $before,
            'growth' => $this->rate($total - $before, $before),
            'joined' => $this->monthly(fn ($a, $b) => $this->members()->whereBetween('joined_on', [$a->toDateString(), $b->toDateString()])->count()),
            'skills' => DB::table('member_skill')->join('skills', 'skills.id', '=', 'member_skill.skill_id')
                ->whereIn('member_skill.member_id', $this->memberIds())
                ->select('skills.name as label', DB::raw('count(*) as value'))
                ->groupBy('skills.name')->orderByDesc('value')->limit(8)->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    protected function reporting(): array
    {
        $period = fn () => $this->reports()->where('activity_date', '>=', $this->from->toDateString());
        $counted = $period()->counted();
        $approved = (clone $counted)->count();
        $returned = $period()->where('status', 'rejected')->count();

        return [
            'submitted' => $period()->where('status', '!=', 'draft')->count(),
            'approved' => $approved,
            'waiting' => $period()->whereIn('status', ['submitted', 'under_review'])->count(),
            'approval_rate' => $this->rate($approved, $approved + $returned),
            'performances' => (clone $counted)->whereIn('activity_type', ActivityReport::PERFORMANCE_TYPES)->count(),
            'performers' => (int) (clone $counted)->sum('performers_count'),
            'attendance' => (int) (clone $counted)->sum('attendance_count'),
            'souls' => (int) (clone $counted)->sum('souls_won'),
            'trend' => $this->monthly(fn ($a, $b) => $this->reports()->counted()->whereBetween('activity_date', [$a->toDateString(), $b->toDateString()])->count()),
            'types' => (clone $counted)->select('activity_type', DB::raw('count(*) as value'))->groupBy('activity_type')->orderByDesc('value')->get()
                ->map(fn ($r) => ['label' => ActivityReport::TYPES[$r->activity_type] ?? 'Other', 'value' => (int) $r->value])->all(),
        ];
    }

    protected function training(): array
    {
        $enrolments = Enrolment::whereIn('member_id', $this->memberIds())->where('enrolments.created_at', '>=', $this->from);
        $enrolled = (clone $enrolments)->where('status', '!=', 'withdrawn')->count();
        $attendance = SessionAttendance::whereIn('member_id', $this->memberIds())->where('created_at', '>=', $this->from);

        $assignments = DB::table('assignments')->join('enrolments', 'enrolments.course_id', '=', 'assignments.course_id')
            ->whereIn('enrolments.member_id', $this->memberIds())->where('enrolments.status', '!=', 'withdrawn')
            ->where('enrolments.created_at', '>=', $this->from);
        $expected = (clone $assignments)->count();
        $handedIn = (clone $assignments)->whereExists(fn ($q) => $q->from('assignment_submissions')
            ->whereColumn('assignment_submissions.assignment_id', 'assignments.id')
            ->whereColumn('assignment_submissions.member_id', 'enrolments.member_id'))->count();

        return [
            'courses' => $this->inScope(Course::query()->published())->where('created_at', '>=', $this->from)->count(),
            'enrolled' => $enrolled,
            'completed' => (clone $enrolments)->where('status', 'completed')->count(),
            'completion_rate' => $this->rate((clone $enrolments)->where('status', 'completed')->count(), $enrolled),
            'joined_live' => (clone $attendance)->whereIn('status', ['joined', 'attended'])->count(),
            'attended' => (clone $attendance)->where('status', 'attended')->count(),
            'assignment_rate' => $this->rate($handedIn, $expected),
            'trend' => $this->monthly(fn ($a, $b) => Enrolment::whereIn('member_id', $this->memberIds())->whereBetween('created_at', [$a, $b])->count()),
        ];
    }

    protected function cbt(): array
    {
        $finished = ExamAttempt::whereIn('exam_attempts.member_id', $this->memberIds())->where('exam_attempts.status', '!=', 'in_progress')->where('exam_attempts.submitted_at', '>=', $this->from);
        $marked = (clone $finished)->whereNotNull('passed');

        return [
            'candidates' => (clone $finished)->distinct()->count('exam_attempts.member_id'),
            'attempts' => (clone $finished)->count(),
            'average' => ($avg = (clone $marked)->avg('percent')) !== null ? (int) round($avg) : null,
            'pass_rate' => $this->rate((clone $marked)->where('passed', true)->count(), (clone $marked)->count()),
            'awaiting_marking' => (clone $finished)->where('needs_marking', true)->count(),
            'exams' => (clone $finished)->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
                ->select('exams.title as label', DB::raw('count(*) as value'), DB::raw('avg(case when passed = 1 then 100.0 when passed = 0 then 0 end) as pass'))
                ->groupBy('exams.id', 'exams.title')->orderByDesc('value')->limit(6)->get()
                ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value, 'note' => $r->pass !== null ? round($r->pass).'% passed' : 'not marked yet'])->all(),
        ];
    }

    protected function events(): array
    {
        $events = $this->inScope(Event::query())->whereIn('status', ['published', 'cancelled'])->where('starts_at', '>=', $this->from);
        $registrations = EventRegistration::whereIn('event_id', (clone $events)->select('id'))->where('status', '!=', 'cancelled');
        $past = EventRegistration::whereIn('event_id', (clone $events)->where('starts_at', '<', now())->select('id'))->where('status', '!=', 'cancelled');

        return [
            'held' => (clone $events)->where('status', 'published')->where('starts_at', '<', now())->count(),
            'upcoming' => (clone $events)->where('status', 'published')->where('starts_at', '>=', now())->count(),
            'registrations' => (clone $registrations)->count(),
            'attended' => (clone $past)->whereNotNull('attended_at')->count(),
            'attendance_rate' => $this->rate((clone $past)->whereNotNull('attended_at')->count(), (clone $past)->count()),
        ];
    }

    protected function recognition(): array
    {
        return [
            'certificates' => Certificate::whereIn('member_id', $this->memberIds())->where('status', 'valid')->where('issued_on', '>=', $this->from->toDateString())->count(),
            'achievements' => MemberAchievement::whereIn('member_id', $this->memberIds())->where('awarded_on', '>=', $this->from->toDateString())->count(),
        ];
    }

    /** Media is national: GODRAM TV belongs to everyone. Views are counted by YouTube, not here. */
    protected function media(): array
    {
        return [
            'videos' => Video::where('status', 'published')->where('published_at', '>=', $this->from)->count(),
            'stories' => Story::where('status', 'published')->where('published_at', '>=', $this->from)->count(),
            'streams' => $this->inScope(Event::query())->whereNotNull('stream_url')->where('status', 'published')->where('starts_at', '>=', $this->from)->count(),
        ];
    }

    /** One row per unit directly below, so leaders can compare. */
    protected function children(): array
    {
        return $this->scope->children()->where('is_active', true)->orderBy('name')->get()->map(function (OrgUnit $child) {
            $reports = $this->reports($child)->counted()->where('activity_date', '>=', $this->from->toDateString());
            $members = $this->members($child);

            return [
                'unit' => $child,
                'members' => (clone $members)->count(),
                'new' => (clone $members)->where('joined_on', '>=', $this->from->toDateString())->count(),
                'reports' => (clone $reports)->count(),
                'attendance' => (int) (clone $reports)->sum('attendance_count'),
                'enrolled' => Enrolment::whereIn('member_id', $members->select('members.id'))->where('status', '!=', 'withdrawn')->where('created_at', '>=', $this->from)->count(),
                'last_report' => ($d = $this->reports($child)->counted()->max('activity_date')) ? Carbon::parse($d) : null,
            ];
        })->all();
    }
}
