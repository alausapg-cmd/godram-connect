<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Awaiting approval',
        'published' => 'Published',
        'rejected' => 'Returned',
        'archived' => 'Archived',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_pinned' => 'boolean',
            'is_demo' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->whereHas('targets', fn ($q) => $q->where('audience', 'public'));
    }

    /**
     * Announcements a signed-in user should see: public ones, ones aimed at
     * their Assembly or any unit above it, and ones aimed at a role they hold.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->public();
        }

        $unitIds = [];
        if ($assembly = $user->member?->assembly()) {
            $unitIds = array_merge($assembly->ancestorIds(), [$assembly->id]);
        }
        $roleKeys = $user->activeAssignments()->pluck('role.key')->unique()->values()->all();

        return $query->whereHas('targets', function ($q) use ($unitIds, $roleKeys) {
            $q->where('audience', 'public')
                ->orWhere(fn ($q) => $q->where('audience', 'org_unit')->whereIn('org_unit_id', $unitIds ?: [0]))
                ->orWhere(fn ($q) => $q->where('audience', 'role')->whereIn('role_key', $roleKeys ?: ['']));
        });
    }

    public function isPublic(): bool
    {
        return $this->targets->contains('audience', 'public');
    }

    public function audienceLabel(): string
    {
        return $this->targets->map(function (AnnouncementTarget $t) {
            return match ($t->audience) {
                'public' => 'Everyone (public)',
                'org_unit' => $t->orgUnit?->fullName() ?? 'Unit',
                'role' => (Role::where('key', $t->role_key)->value('name') ?? $t->role_key).'s',
                default => $t->audience,
            };
        })->implode(', ');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
