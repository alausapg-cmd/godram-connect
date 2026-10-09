<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A milestone on a member's achievement timeline. */
class MemberAchievement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['awarded_on' => 'date'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AchievementRule::class, 'achievement_rule_id');
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
