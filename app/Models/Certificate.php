<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A GODRAM certificate. Anyone can check it at /verify/{number}; only limited details are shown there. */
class Certificate extends Model
{
    public const KINDS = [
        'examination' => 'Examination success',
        'training' => 'Training completion',
        'achievement' => 'Achievement',
        'recognition' => 'Special recognition',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'valid'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'signatories' => 'array', 'is_demo' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AchievementRule::class, 'achievement_rule_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function verifyUrl(): string
    {
        return route('certificates.verify', $this->number);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    /** "Adebayo O." — enough to match a printed certificate without exposing the full record. */
    public function publicName(): string
    {
        $parts = preg_split('/\s+/', trim($this->recipient_name));
        $last = array_pop($parts);

        return $parts ? implode(' ', $parts).' '.mb_substr($last, 0, 1).'.' : $last;
    }
}
