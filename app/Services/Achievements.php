<?php

namespace App\Services;

use App\Models\AchievementRule;
use App\Models\ActivityReport;
use App\Models\Enrolment;
use App\Models\ExamAttempt;
use App\Models\Member;
use App\Models\MemberAchievement;
use App\Models\SessionAttendance;
use Illuminate\Support\Facades\DB;

/** Checks members against the achievement rules and records new milestones. */
class Achievements
{
    public function __construct(protected AuditLogger $audit) {}

    public function metric(Member $member, string $metric): int
    {
        $userId = $member->user?->id ?? 0;

        return match ($metric) {
            'courses_completed' => Enrolment::where('member_id', $member->id)->where('status', 'completed')->count(),
            'exams_passed' => ExamAttempt::where('member_id', $member->id)->where('passed', true)
                ->whereHas('exam', fn ($q) => $q->where('mode', 'certification'))->distinct()->count('exam_id'),
            'live_classes' => SessionAttendance::where('member_id', $member->id)->where('status', 'attended')->count(),
            'performances' => $member->reports()->counted()->count(),
            'reports_approved' => ActivityReport::where('created_by', $userId)->counted()->count(),
            'reporting_months' => ActivityReport::where('created_by', $userId)->counted()->pluck('activity_date')
                ->map(fn ($d) => $d?->format('Y-m'))->filter()->unique()->count(),
            'years_of_service' => $member->joined_on ? (int) $member->joined_on->diffInYears(now()) : 0,
            default => 0,
        };
    }

    /** @return int number of new achievements */
    public function evaluate(Member $member): int
    {
        $held = MemberAchievement::where('member_id', $member->id)->pluck('achievement_rule_id')->all();
        $new = 0;
        foreach (AchievementRule::where('is_active', true)->whereNotIn('id', $held ?: [0])->get() as $rule) {
            if ($this->metric($member, $rule->metric) < $rule->threshold) {
                continue;
            }
            DB::transaction(function () use ($rule, $member) {
                $certificate = $rule->issues_certificate ? app(Certificates::class)->forAchievement($rule, $member) : null;
                MemberAchievement::create([
                    'member_id' => $member->id,
                    'achievement_rule_id' => $rule->id,
                    'title' => $rule->name,
                    'description' => $rule->description,
                    'kind' => $rule->kind,
                    'awarded_on' => today(),
                    'certificate_id' => $certificate?->id,
                ]);
                $this->audit->log('achievement.awarded', $member, $member->full_name.' reached "'.$rule->name.'"', ['rule' => $rule->id], $member->assembly());
            });
            $new++;
        }

        return $new;
    }

    public function evaluateAll(): int
    {
        $total = 0;
        Member::where('status', 'active')->whereNotNull('approved_at')->with('user')->chunkById(200, function ($members) use (&$total) {
            foreach ($members as $member) {
                $total += $this->evaluate($member);
            }
        });

        return $total;
    }
}
