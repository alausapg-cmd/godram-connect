<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'member_id', 'is_active', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    /** Per-request cache of active role assignments. */
    protected ?Collection $assignmentCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return Collection<int, RoleAssignment> */
    public function activeAssignments(): Collection
    {
        if ($this->assignmentCache === null) {
            $this->assignmentCache = $this->member_id
                ? RoleAssignment::active()
                    ->where('member_id', $this->member_id)
                    ->with(['role.permissions', 'orgUnit'])
                    ->get()
                : collect();
        }

        return $this->assignmentCache;
    }

    public function forgetAssignments(): void
    {
        $this->assignmentCache = null;
    }

    public function hasRole(string $key): bool
    {
        return $this->activeAssignments()->contains(fn ($a) => $a->role->key === $key);
    }

    public function isCoordinator(): bool
    {
        return $this->activeAssignments()->contains(fn ($a) => str_ends_with($a->role->key, '_coordinator'));
    }

    public function primaryTitle(): string
    {
        $order = ['national_coordinator', 'regional_coordinator', 'district_coordinator', 'assembly_coordinator'];
        $assignments = $this->activeAssignments()->sortBy(fn ($a) => array_search($a->role->key, $order) === false ? 99 : array_search($a->role->key, $order));

        return $assignments->first()?->title() ?? 'GODRAM Member';
    }
}
