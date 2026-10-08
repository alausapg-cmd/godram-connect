<?php

namespace App\Http\Controllers;

use App\Models\ActivityReport;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\OrgUnit;
use App\Models\TransferRequest;
use App\Services\Access;
use App\Services\MembershipService;
use App\Services\ReportWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Role-specific dashboards: what is happening in my area and what needs me. */
class DashboardController extends Controller
{
    public function __construct(
        protected Access $access,
        protected ReportWorkflow $workflow,
        protected MembershipService $membership,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $member = $user->member?->load('currentPlacement.orgUnit', 'skills');

        $scopes = $this->access->units($user, 'members.view')
            ->merge($this->access->units($user, 'reports.view'))
            ->unique('id')->sortBy('depth')->values();
        $scope = $request->filled('scope') ? $scopes->firstWhere('id', (int) $request->input('scope')) : $scopes->first();

        $data = [
            'user' => $user,
            'member' => $member,
            'assembly' => $member?->assembly(),
            'announcements' => Announcement::live()->visibleTo($user)->orderByDesc('is_pinned')->latest('published_at')->limit(4)->get(),
            'myReports' => $member ? $member->reports()->counted()->latest('activity_date')->limit(5)->get() : collect(),
            'scopes' => $scopes,
            'scope' => $scope,
        ];

        if ($scope) {
            $data = array_merge($data, $this->leadership($request, $scope));
        }

        return view('dashboard', $data);
    }

    protected function leadership(Request $request, OrgUnit $scope): array
    {
        $user = $request->user();
        $members = Member::approved()->where('status', '!=', 'archived')->placedWithin($scope);
        $since = now()->subDays(90)->toDateString();
        $reports = ActivityReport::withinPaths([$scope->path]);

        $children = $scope->children()->where('is_active', true)->get()->map(function (OrgUnit $child) use ($since) {
            $lastReport = ActivityReport::withinPaths([$child->path])->counted()->max('activity_date');

            return (object) [
                'unit' => $child,
                'members' => Member::approved()->where('status', 'active')->placedWithin($child)->count(),
                'reports' => ActivityReport::withinPaths([$child->path])->counted()->where('activity_date', '>=', $since)->count(),
                'last_report' => $lastReport ? \Illuminate\Support\Carbon::parse($lastReport) : null,
            ];
        });

        $toReview = ActivityReport::withinPaths([$scope->path])->whereIn('status', ['submitted', 'under_review'])
            ->with('orgUnit')->get()->filter(fn ($r) => $this->workflow->canReview($user, $r));

        $transfers = TransferRequest::where('status', 'pending')->with('toUnit', 'fromUnit')->get()
            ->filter(fn ($t) => $this->membership->canDecideTransfer($user, $t));

        $signups = $this->access->can($user, 'members.approve_signup', $scope)
            ? Member::pending()->where('status', '!=', 'archived')->placedWithin($scope)->count()
            : 0;

        $returned = ActivityReport::where('created_by', $user->id)->where('status', 'rejected')->get();
        $drafts = ActivityReport::where('created_by', $user->id)->where('status', 'draft')->count();

        // What needs attention, in plain words.
        $attention = collect();
        foreach ($returned as $r) {
            $attention->push(['tone' => 'warning', 'text' => 'Your report "'.$r->displayTitle().'" was returned for correction.', 'url' => route('reports.edit', $r), 'action' => 'Correct it']);
        }
        if ($toReview->isNotEmpty()) {
            $attention->push(['tone' => 'action', 'text' => $toReview->count().' '.str('report')->plural($toReview->count()).' waiting for your review.', 'url' => route('reports.index', ['tab' => 'review']), 'action' => 'Review']);
        }
        if ($transfers->isNotEmpty()) {
            $attention->push(['tone' => 'action', 'text' => $transfers->count().' '.str('transfer')->plural($transfers->count()).' waiting for your decision.', 'url' => route('transfers.index'), 'action' => 'Decide']);
        }
        if ($signups) {
            $attention->push(['tone' => 'action', 'text' => $signups.' new '.str('sign-up')->plural($signups).' to confirm.', 'url' => route('signups.index'), 'action' => 'Confirm']);
        }
        $quiet = $children->filter(fn ($c) => ! $c->last_report || $c->last_report->lt(now()->subDays(60)));
        if ($quiet->isNotEmpty() && $scope->type !== OrgUnit::ASSEMBLY) {
            $attention->push(['tone' => 'info', 'text' => $quiet->count().' of '.$children->count().' '.str($scope->childType() ?? 'unit')->plural().' have no approved report in the last 60 days: '.$quiet->take(4)->map(fn ($c) => $c->unit->name)->join(', ').($quiet->count() > 4 ? ' and others' : '').'.', 'url' => null, 'action' => null]);
        }

        $trend = $this->monthlyReports($scope);

        $skills = DB::table('member_skill')
            ->join('skills', 'skills.id', '=', 'member_skill.skill_id')
            ->whereIn('member_skill.member_id', (clone $members)->select('members.id'))
            ->select('skills.name', DB::raw('count(*) as total'))
            ->groupBy('skills.name')->orderByDesc('total')->limit(6)->get();

        return [
            'stats' => [
                'members' => (clone $members)->count(),
                'active' => (clone $members)->where('status', 'active')->count(),
                'new' => (clone $members)->where('joined_on', '>=', now()->subDays(90)->toDateString())->count(),
                'reports' => (clone $reports)->counted()->where('activity_date', '>=', $since)->count(),
                'attendance' => (int) (clone $reports)->counted()->where('activity_date', '>=', $since)->sum('attendance_count'),
                'souls' => (int) (clone $reports)->counted()->where('activity_date', '>=', $since)->sum('souls_won'),
            ],
            'children' => $children,
            'attention' => $attention,
            'drafts' => $drafts,
            'trend' => $trend,
            'skills' => $skills,
            'recent' => ActivityReport::withinPaths([$scope->path])->counted()->with('orgUnit')->latest('activity_date')->limit(6)->get(),
        ];
    }

    protected function monthlyReports(OrgUnit $scope): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $months[] = [
                'label' => $start->format('M'),
                'count' => ActivityReport::withinPaths([$scope->path])->counted()
                    ->whereBetween('activity_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->count(),
            ];
        }

        return $months;
    }
}
