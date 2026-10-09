<?php

namespace App\Models;

use App\Models\Concerns\HasCover;
use App\Models\Concerns\HasSlug;
use App\Services\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/** A GODRAM Virtual Academy training programme. */
class Course extends Model
{
    use HasCover, HasSlug;

    public const COVER_TYPE = 'course';

    public const KINDS = [
        'recorded' => 'Self-paced course',
        'live' => 'Live training',
        'blended' => 'Live and self-paced',
    ];

    public const STATUSES = ['draft' => 'Draft', 'published' => 'Open', 'archived' => 'Archived'];

    protected $guarded = ['id', 'slug'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'enrol_by' => 'date',
            'published_at' => 'datetime', 'is_public' => 'boolean', 'is_demo' => 'boolean',
        ];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CourseTarget::class);
    }

    public function facilitators(): HasMany
    {
        return $this->hasMany(CourseFacilitator::class)->orderBy('sort');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class)->orderBy('sort')->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(CourseResource::class)->orderBy('sort')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('sort')->orderBy('id');
    }

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(AssignmentSubmission::class, Assignment::class);
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CourseQuestion::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Programmes a person may see: public ones for guests; for members, those aimed at them. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $query->published();
        if (! $user) {
            return $query->where('is_public', true);
        }

        $assembly = $user->member?->assembly();
        $unitIds = $assembly ? array_merge($assembly->ancestorIds(), [$assembly->id]) : [];
        $paths = $assembly ? collect(explode('/', trim($assembly->path, '/')))
            ->reduce(fn ($carry, $id) => $carry->push(($carry->last() ?? '/').$id.'/'), collect())->all() : [];
        $roleKeys = $user->activeAssignments()->pluck('role.key')->unique()->values()->all();

        return $query->where(function ($q) use ($unitIds, $paths, $roleKeys, $user) {
            $q->where('is_public', true)
                // aimed at everyone under the organiser, and the member is under it
                ->orWhere(fn ($q) => $q->whereDoesntHave('targets')->whereHas('orgUnit', fn ($q) => $q->whereIn('path', $paths ?: ['-'])))
                ->orWhereHas('targets', fn ($q) => $q
                    ->where(fn ($q) => $q->where('audience', 'org_unit')->whereIn('org_unit_id', $unitIds ?: [0]))
                    ->orWhere(fn ($q) => $q->where('audience', 'role')->whereIn('role_key', $roleKeys ?: [''])))
                ->orWhereHas('enrolments', fn ($q) => $q->where('member_id', $user->member_id ?? 0));
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->canBeManagedBy($user) || $this->isFacilitator($user)) {
            return true;
        }

        return static::visibleTo($user)->whereKey($this->id)->exists();
    }

    /** Training administrators and coordinators who run training for the organising unit or above. */
    public function canBeManagedBy(?User $user): bool
    {
        return $user && $this->orgUnit && app(Access::class)->can($user, 'training.manage', $this->orgUnit);
    }

    public function isFacilitator(?User $user): bool
    {
        return $user && $user->member_id && $this->facilitators->contains('member_id', $user->member_id);
    }

    /** Facilitators teach; managers can do everything facilitators can. */
    public function canTeach(?User $user): bool
    {
        return $this->isFacilitator($user) || $this->canBeManagedBy($user);
    }

    public function enrolmentFor(?User $user): ?Enrolment
    {
        if (! $user?->member_id) {
            return null;
        }

        return $this->relationLoaded('enrolments')
            ? $this->enrolments->firstWhere('member_id', $user->member_id)
            : $this->enrolments()->where('member_id', $user->member_id)->first();
    }

    public function acceptsEnrolment(): bool
    {
        return $this->status === 'published'
            && (! $this->enrol_by || $this->enrol_by->endOfDay()->isFuture())
            && (! $this->ends_on || $this->ends_on->endOfDay()->isFuture());
    }

    /** National, Regional or District training, by who runs it. */
    public function levelLabel(): string
    {
        return match ($this->orgUnit?->type) {
            OrgUnit::NATIONAL => 'National training',
            OrgUnit::REGION => 'Regional training',
            OrgUnit::DISTRICT => 'District training',
            default => 'Assembly training',
        };
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    public function audienceLabel(): string
    {
        $targets = $this->relationLoaded('targets') ? $this->targets : $this->targets()->get();
        if ($targets->isEmpty()) {
            return 'All members in '.$this->orgUnit?->fullName();
        }
        $roles = Role::whereIn('key', $targets->where('audience', 'role')->pluck('role_key'))->pluck('name');
        $units = OrgUnit::whereIn('id', $targets->where('audience', 'org_unit')->pluck('org_unit_id'))->get()->map->fullName();

        return $units->toBase()->merge($roles->map(fn ($r) => $r.'s')->all())->join(', ', ' and ');
    }

    /** Lessons in teaching order, across sessions. */
    public function orderedLessons(): Collection
    {
        $sessions = $this->relationLoaded('sessions') ? $this->sessions : $this->sessions()->with('lessons')->get();

        return $sessions->flatMap(fn (CourseSession $s) => $s->lessons)->values();
    }

    public function outcomeList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->outcomes))));
    }

    public function nextLiveSession(): ?CourseSession
    {
        return $this->sessions()->whereNotNull('live_at')
            ->where(fn ($q) => $q->where('live_ends_at', '>=', now())->orWhere(fn ($q) => $q->whereNull('live_ends_at')->where('live_at', '>=', now()->subHours(3))))
            ->orderBy('live_at')->first();
    }
}
