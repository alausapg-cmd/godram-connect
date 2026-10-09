<?php

namespace Database\Seeders;

use App\Models\AchievementRule;
use Illuminate\Database\Seeder;

/** Starting achievement rules from the brief. Administrators can change them under Certificates. Safe to re-run. */
class AchievementRuleSeeder extends Seeder
{
    public const RULES = [
        ['First training completed', 'Completed a GODRAM Virtual Academy training from start to finish.', 'training', 'courses_completed', 1, false],
        ['Committed learner', 'Completed three Academy trainings.', 'training', 'courses_completed', 3, true],
        ['Certified drama minister', 'Passed a GODRAM certification examination.', 'excellence', 'exams_passed', 1, false],
        ['Faithful in class', 'Attended ten live classes, confirmed by a facilitator.', 'training', 'live_classes', 10, true],
        ['On stage for the Gospel', 'Took part in five performances recorded in approved reports.', 'creative', 'performances', 5, false],
        ['Creative service', 'Took part in twenty performances recorded in approved reports.', 'creative', 'performances', 20, true],
        ['Reporting excellence', 'Twelve months with an approved activity report.', 'reporting', 'reporting_months', 12, true],
        ['Five years of service', 'Five years in the GODRAM ministry.', 'service', 'years_of_service', 5, false],
        ['Ten years of service', 'Ten years in the GODRAM ministry.', 'service', 'years_of_service', 10, true],
    ];

    public function run(): void
    {
        foreach (self::RULES as [$name, $description, $kind, $metric, $threshold, $certificate]) {
            AchievementRule::firstOrCreate(['metric' => $metric, 'threshold' => $threshold], [
                'name' => $name, 'description' => $description, 'kind' => $kind, 'issues_certificate' => $certificate,
            ]);
        }
    }
}
