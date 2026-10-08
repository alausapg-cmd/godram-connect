<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'to_unit_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function crossesDistricts(): bool
    {
        return $this->fromUnit->ancestorOfType(OrgUnit::DISTRICT)?->id !== $this->toUnit->ancestorOfType(OrgUnit::DISTRICT)?->id;
    }
}
