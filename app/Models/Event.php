<?php

namespace App\Models;

use App\Models\Concerns\HasCover;
use App\Models\Concerns\HasSlug;
use App\Services\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    use HasCover, HasSlug;

    public const COVER_TYPE = 'event';

    public const TYPES = [
        'performance' => 'Performance',
        'training' => 'Training',
        'workshop' => 'Workshop',
        'convention' => 'Convention',
        'film_premiere' => 'Film premiere',
        'competition' => 'Competition',
        'outreach' => 'Outreach',
        'district_programme' => 'District programme',
        'regional_programme' => 'Regional programme',
        'national_programme' => 'National programme',
    ];

    public const PLATFORMS = [
        'youtube' => 'YouTube Live',
        'facebook' => 'Facebook Live',
        'zoom' => 'Zoom',
        'meet' => 'Google Meet',
        'teams' => 'Microsoft Teams',
        'other' => 'Online',
    ];

    public const ROLES = ['host' => 'Host', 'facilitator' => 'Facilitator', 'speaker' => 'Speaker', 'performer' => 'Performer'];

    /** Without an end time, an event is treated as lasting this many hours. */
    public const DEFAULT_HOURS = 3;

    protected $guarded = ['id', 'slug'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'published_at' => 'datetime',
            'is_online' => 'boolean',
            'registration_open' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(EventPerson::class)->orderBy('sort')->orderBy('id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function activeRegistrations(): HasMany
    {
        return $this->registrations()->where('status', '!=', 'cancelled');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class)->where('status', 'published');
    }

    /** Published events this person may see: public ones, and members-only ones when signed in. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->whereIn('status', ['published', 'cancelled'])
            ->when(! $user, fn ($q) => $q->where('visibility', 'public'));
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('ends_at', '>=', now())
            ->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->subHours(self::DEFAULT_HOURS))))
            ->orderBy('starts_at');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('ends_at', '<', now())
            ->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '<', now()->subHours(self::DEFAULT_HOURS))))
            ->orderByDesc('starts_at');
    }

    public function scopeStreamed(Builder $query): Builder
    {
        return $query->whereNotNull('stream_url');
    }

    public function endsAt(): Carbon
    {
        return $this->ends_at ?? $this->starts_at->copy()->addHours(self::DEFAULT_HOURS);
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && $this->stream_url && now()->between($this->starts_at, $this->endsAt());
    }

    public function isPast(): bool
    {
        return $this->endsAt()->isPast();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function platformLabel(): ?string
    {
        return $this->stream_url ? (self::PLATFORMS[$this->stream_platform] ?? 'Online') : null;
    }

    public function organiserName(): string
    {
        return $this->orgUnit?->fullName() ?? 'GODRAM';
    }

    /** A YouTube video id we can embed for the live stream, when the link is to a single video. */
    public function streamYoutubeId(): ?string
    {
        return $this->stream_platform === 'youtube' ? Video::youtubeIdFrom((string) $this->stream_url) : null;
    }

    public function placesLeft(): ?int
    {
        return $this->capacity ? max(0, $this->capacity - $this->activeRegistrations()->count()) : null;
    }

    public function acceptsRegistrations(): bool
    {
        return $this->registration_open
            && $this->status === 'published'
            && ! $this->isPast()
            && (! $this->registration_closes_at || $this->registration_closes_at->isFuture())
            && $this->placesLeft() !== 0;
    }

    public function isVisibleTo(?User $user): bool
    {
        if (in_array($this->status, ['published', 'cancelled']) && ($this->visibility === 'public' || $user)) {
            return true;
        }

        return $this->canBeManagedBy($user);
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $access = app(Access::class);

        return $access->can($user, 'media.manage')
            || ($this->orgUnit && $access->can($user, 'events.create', $this->orgUnit));
    }

    /** Link that adds the event to Google Calendar. */
    public function googleCalendarUrl(): string
    {
        $format = fn (Carbon $t) => $t->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $this->title,
            'dates' => $format($this->starts_at).'/'.$format($this->endsAt()),
            'details' => \Illuminate\Support\Str::limit($this->description, 500)."\n\n".route('events.show', $this),
            'location' => $this->is_online ? ($this->stream_url ?? 'Online') : trim($this->location.', '.$this->address, ', '),
        ]);
    }
}
