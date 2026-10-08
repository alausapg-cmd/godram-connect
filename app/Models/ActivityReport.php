<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityReport extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'rejected' => 'Returned for correction',
        'published' => 'Published',
        'archived' => 'Archived',
    ];

    public const TYPES = [
        'drama_presentation' => 'Drama presentation',
        'evangelistic_performance' => 'Evangelistic performance',
        'church_programme' => 'Church programme',
        'community_outreach' => 'Community outreach',
        'film_production' => 'Film production',
        'film_screening' => 'Film screening',
        'theatre_performance' => 'Theatre performance',
        'training' => 'Training',
        'workshop' => 'Workshop',
        'special_event' => 'Special event',
        'youth_programme' => 'Youth creative programme',
        'childrens_programme' => "Children's programme",
        'regional_performance' => 'Regional performance',
        'national_programme' => 'National programme',
        'other' => 'Other approved activity',
    ];

    /** Types where a performer count and audience make sense. */
    public const PERFORMANCE_TYPES = [
        'drama_presentation', 'evangelistic_performance', 'church_programme', 'community_outreach',
        'film_screening', 'theatre_performance', 'regional_performance', 'national_programme', 'special_event',
        'youth_programme', 'childrens_programme',
    ];

    /** Types where an evangelistic response is recorded. */
    public const OUTREACH_TYPES = ['evangelistic_performance', 'community_outreach', 'film_screening'];

    protected $guarded = ['id', 'reference'];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'is_public_highlight' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ActivityReport $report) {
            $report->forceFill([
                'reference' => 'RPT-'.now()->format('Y').'-'.str_pad((string) $report->id, 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function productionTeam(): BelongsTo
    {
        return $this->belongsTo(ProductionTeam::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReportEvent::class)->orderBy('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReportMedia::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'report_participants')->withPivot('role');
    }

    public function scopeWithinPaths(Builder $query, array $paths): Builder
    {
        if (empty($paths)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('orgUnit', function ($q) use ($paths) {
            $q->where(function ($q) use ($paths) {
                foreach ($paths as $path) {
                    $q->orWhere('path', 'like', $path.'%');
                }
            });
        });
    }

    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'published']);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->activity_type] ?? 'Activity';
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->activity_type ? $this->typeLabel() : 'Untitled report');
    }
}
