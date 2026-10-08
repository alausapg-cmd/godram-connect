<?php

namespace App\Services;

use App\Models\ActivityReport;
use App\Models\ReportEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft → Submitted → Under review → Approved → Published / Archived,
 * with Returned → Corrected → Resubmitted. Every step is recorded.
 */
class ReportWorkflow
{
    public function __construct(
        protected Access $access,
        protected AuditLogger $audit,
    ) {}

    public function canEdit(?User $user, ActivityReport $report): bool
    {
        return $report->isEditable() && $this->access->can($user, 'reports.create', $report->orgUnit);
    }

    public function canReview(?User $user, ActivityReport $report): bool
    {
        return $user
            && in_array($report->status, ['submitted', 'under_review'])
            && $report->created_by !== $user->id
            // Reports are approved by the level directly above: District for Assembly reports,
            // Region for District reports, National for Regional reports. Higher levels view and comment.
            && $this->access->holdsAt($user, 'reports.approve', $report->orgUnit->parent ?? $report->orgUnit);
    }

    public function canPublish(?User $user, ActivityReport $report): bool
    {
        return $report->status === 'approved' && $this->access->can($user, 'reports.publish', $report->orgUnit);
    }

    public function canArchive(?User $user, ActivityReport $report): bool
    {
        return in_array($report->status, ['approved', 'published']) && $this->access->can($user, 'reports.publish', $report->orgUnit);
    }

    public function submit(ActivityReport $report, User $by): void
    {
        $missing = collect(['activity_type' => 'activity type', 'title' => 'title', 'activity_date' => 'date', 'location' => 'location', 'description' => 'description'])
            ->filter(fn ($label, $field) => blank($report->{$field}));
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'report' => 'Please add the '.$missing->values()->join(', ', ' and ').' before submitting.',
            ]);
        }

        $resubmission = $report->status === 'rejected';
        $this->transition($report, 'submitted', $by, $resubmission ? 'resubmitted' : 'submitted', null, ['submitted_at' => now()]);
    }

    public function startReview(ActivityReport $report, User $by): void
    {
        if ($report->status === 'submitted') {
            $this->transition($report, 'under_review', $by, 'review_started', null, ['reviewer_id' => $by->id]);
        }
    }

    public function approve(ActivityReport $report, User $by, ?string $note = null): void
    {
        $this->transition($report, 'approved', $by, 'approved', $note, ['reviewer_id' => $by->id, 'reviewed_at' => now(), 'reject_reason' => null]);
    }

    public function reject(ActivityReport $report, User $by, string $reason): void
    {
        $this->transition($report, 'rejected', $by, 'returned', $reason, ['reviewer_id' => $by->id, 'reviewed_at' => now(), 'reject_reason' => $reason]);
    }

    public function publish(ActivityReport $report, User $by): void
    {
        $this->transition($report, 'published', $by, 'published', null, ['is_public_highlight' => true, 'published_at' => now()]);
    }

    public function archive(ActivityReport $report, User $by): void
    {
        $this->transition($report, 'archived', $by, 'archived', null, ['is_public_highlight' => false]);
    }

    /** Anyone who can see a submitted report may comment on it; comments never change its status. */
    public function canComment(?User $user, ActivityReport $report): bool
    {
        return $user && $report->status !== 'draft' && $this->access->can($user, 'reports.view', $report->orgUnit);
    }

    public function comment(ActivityReport $report, User $by, string $note): void
    {
        ReportEvent::create([
            'activity_report_id' => $report->id,
            'user_id' => $by->id,
            'action' => 'commented',
            'note' => $note,
        ]);
    }

    protected function transition(ActivityReport $report, string $to, User $by, string $action, ?string $note, array $extra = []): void
    {
        DB::transaction(function () use ($report, $to, $by, $action, $note, $extra) {
            $from = $report->status;
            $report->forceFill(array_merge($extra, ['status' => $to]))->save();

            ReportEvent::create([
                'activity_report_id' => $report->id,
                'user_id' => $by->id,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);

            if (in_array($action, ['submitted', 'resubmitted', 'approved', 'returned', 'published', 'archived'])) {
                $this->audit->log('report.'.$action, $report, ucfirst($action).': '.$report->reference.' '.$report->displayTitle(), ['note' => $note], $report->orgUnit, $by->id);
            }
        });
    }
}
