<?php

namespace App\Http\Controllers;

use App\Models\OrgUnit;
use App\Services\Access;
use App\Services\Analytics;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Trends for leaders, for their own part of the tree and anything below it. */
class AnalyticsController extends Controller
{
    public function __construct(protected Access $access, protected Analytics $analytics) {}

    public function show(Request $request)
    {
        [$scope, $months, $scopes] = $this->resolve($request);

        return view('analytics.show', $this->analytics->for($scope, $months) + ['scopes' => $scopes]);
    }

    public function export(Request $request)
    {
        [$scope, $months] = $this->resolve($request);
        $data = $this->analytics->for($scope, $months);
        $name = 'godram-analytics-'.str($scope->name)->slug().'-'.now()->format('Y-m-d').'.csv';

        app(AuditLogger::class)->log('analytics.exported', $scope, 'Analytics exported for '.$scope->fullName(), ['months' => $months], $scope);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['GODRAM CONNECT analytics', $data['scope']->fullName(), 'From '.$data['from']->format('j F Y').' to '.now()->format('j F Y')]);
            fputcsv($out, []);
            fputcsv($out, ['Area', 'Measure', 'Value']);
            $labels = [
                'membership' => ['total' => 'Members', 'active' => 'Active members', 'new' => 'New members', 'growth' => 'Growth (%)'],
                'reporting' => ['submitted' => 'Reports submitted', 'approved' => 'Reports approved', 'waiting' => 'Waiting for review', 'approval_rate' => 'Approval rate (%)', 'performances' => 'Performances', 'performers' => 'Performers', 'attendance' => 'People reached', 'souls' => 'Responded at outreaches'],
                'training' => ['courses' => 'Training programmes', 'enrolled' => 'Enrolments', 'completed' => 'Completed', 'completion_rate' => 'Completion rate (%)', 'joined_live' => 'Joined live classes', 'attended' => 'Attendance confirmed', 'assignment_rate' => 'Assignments handed in (%)'],
                'cbt' => ['candidates' => 'Candidates', 'attempts' => 'Attempts', 'average' => 'Average score (%)', 'pass_rate' => 'Pass rate (%)'],
                'events' => ['held' => 'Events held', 'upcoming' => 'Events coming up', 'registrations' => 'Registrations', 'attended' => 'Attendance recorded', 'attendance_rate' => 'Attendance rate (%)'],
                'recognition' => ['certificates' => 'Certificates issued', 'achievements' => 'Achievements awarded'],
                'media' => ['videos' => 'Videos added (national)', 'stories' => 'Stories published (national)', 'streams' => 'Livestreamed events'],
            ];
            $areas = ['membership' => 'Membership', 'reporting' => 'Reporting', 'training' => 'Training', 'cbt' => 'Examinations', 'events' => 'Events', 'recognition' => 'Recognition', 'media' => 'Media'];
            foreach ($labels as $area => $measures) {
                foreach ($measures as $key => $label) {
                    fputcsv($out, [$areas[$area], $label, $data[$area][$key] ?? '']);
                }
            }
            if ($data['children']) {
                fputcsv($out, []);
                fputcsv($out, ['Unit', 'Members', 'New members', 'Approved reports', 'People reached', 'Enrolments', 'Last approved report']);
                foreach ($data['children'] as $c) {
                    fputcsv($out, [$c['unit']->name, $c['members'], $c['new'], $c['reports'], $c['attendance'], $c['enrolled'], $c['last_report']?->format('Y-m-d') ?? 'None']);
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: OrgUnit, 1: int, 2: Collection} */
    protected function resolve(Request $request): array
    {
        $user = $request->user();
        $scopes = $this->access->units($user, 'analytics.view');
        abort_if($scopes->isEmpty(), 403);

        $scope = $request->filled('scope') ? OrgUnit::findOrFail($request->integer('scope')) : $scopes->first();
        abort_unless($this->access->can($user, 'analytics.view', $scope), 403);
        $months = in_array($request->integer('months'), [3, 6, 12, 24]) ? $request->integer('months') : 12;

        return [$scope, $months, $scopes];
    }
}
