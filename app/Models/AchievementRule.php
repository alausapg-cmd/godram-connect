<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** "20 approved performances → Creative Service Achievement". Checked daily and after results. */
class AchievementRule extends Model
{
    public const KINDS = [
        'training' => 'Training',
        'excellence' => 'Excellence',
        'reporting' => 'Reporting',
        'creative' => 'Creative service',
        'service' => 'Years of service',
        'participation' => 'Participation',
    ];

    public const METRICS = [
        'courses_completed' => 'Academy trainings completed',
        'exams_passed' => 'Certification examinations passed',
        'live_classes' => 'Live classes attended (confirmed by a facilitator)',
        'performances' => 'Performances in approved activity reports',
        'reports_approved' => 'Activity reports written and approved',
        'reporting_months' => 'Months with an approved activity report',
        'years_of_service' => 'Years since joining GODRAM',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['issues_certificate' => false, 'is_active' => true];

    protected function casts(): array
    {
        return ['issues_certificate' => 'boolean', 'is_active' => 'boolean'];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(MemberAchievement::class);
    }

    public function metricLabel(): string
    {
        return self::METRICS[$this->metric] ?? $this->metric;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    public function ruleText(): string
    {
        return $this->threshold.' or more: '.mb_strtolower($this->metricLabel());
    }
}
