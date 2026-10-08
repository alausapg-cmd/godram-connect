<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'transferred' => 'Transferred',
        'temporarily_unavailable' => 'Temporarily Unavailable',
        'retired' => 'Retired from Active Service',
        'archived' => 'Archived',
    ];

    public const GENDERS = ['male' => 'Male', 'female' => 'Female'];

    protected $guarded = ['id', 'member_no'];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'approved_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Member $member) {
            if (! $member->member_no) {
                $member->forceFill([
                    'member_no' => config('godram.member_prefix').str_pad((string) $member->id, 6, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(MemberPlacement::class)->orderByDesc('starts_on')->orderByDesc('id');
    }

    public function currentPlacement(): HasOne
    {
        return $this->hasOne(MemberPlacement::class)->whereNull('ends_on')->latestOfMany();
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->orderBy('sort');
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function activeRoleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class)->active();
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(MemberStatusChange::class)->latest('id');
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(TransferRequest::class)->latest('id');
    }

    public function reports(): BelongsToMany
    {
        return $this->belongsToMany(ActivityReport::class, 'report_participants')->withPivot('role');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->other_names ? $this->other_names.' ' : '').$this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    public function assembly(): ?OrgUnit
    {
        return $this->currentPlacement?->orgUnit;
    }

    public function isPending(): bool
    {
        return $this->approved_at === null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('approved_at');
    }

    /** Members currently placed anywhere inside the given unit's branch. */
    public function scopePlacedWithin(Builder $query, OrgUnit $unit): Builder
    {
        return $query->whereHas('currentPlacement.orgUnit', fn ($q) => $q->where('path', 'like', $unit->path.'%'));
    }

    /** Members placed within any of the given branch paths. */
    public function scopePlacedWithinPaths(Builder $query, array $paths): Builder
    {
        if (empty($paths)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('currentPlacement.orgUnit', function ($q) use ($paths) {
            $q->where(function ($q) use ($paths) {
                foreach ($paths as $path) {
                    $q->orWhere('path', 'like', $path.'%');
                }
            });
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }
        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%$term%")
                ->orWhere('last_name', 'like', "%$term%")
                ->orWhere('other_names', 'like', "%$term%")
                ->orWhere('member_no', 'like', "%$term%")
                ->orWhere('phone', 'like', '%'.ltrim($term, '0+').'%')
                ->orWhere('email', 'like', "%$term%");
        });
    }
}
