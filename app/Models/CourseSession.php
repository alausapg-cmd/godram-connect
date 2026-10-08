<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A part of a training programme. With a date, it is also a live class. */
class CourseSession extends Model
{
    public const LIVE_HOURS = 2;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['live_at' => 'datetime', 'live_ends_at' => 'datetime', 'room_open' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort')->orderBy('id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(CourseResource::class)->orderBy('sort');
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class)->orderBy('sort')->orderBy('id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(SessionAttendance::class);
    }

    public function isLiveSession(): bool
    {
        return $this->live_at !== null;
    }

    public function endsAt()
    {
        return $this->live_ends_at ?? $this->live_at?->copy()->addHours(self::LIVE_HOURS);
    }

    public function isLive(): bool
    {
        return $this->live_at && now()->between($this->live_at->copy()->subMinutes(15), $this->endsAt());
    }

    public function isOver(): bool
    {
        return $this->live_at && now()->greaterThan($this->endsAt());
    }

    /** A YouTube id we can play inside the room, when the class is streamed on YouTube. */
    public function streamYoutubeId(): ?string
    {
        return $this->live_url ? Video::youtubeIdFrom($this->live_url) : null;
    }

    public function platformLabel(): ?string
    {
        return Event::PLATFORMS[$this->live_platform] ?? null;
    }
}
