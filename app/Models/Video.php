<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    use HasSlug;

    public const CATEGORIES = [
        'films' => 'Films',
        'drama_performances' => 'Drama performances',
        'short_films' => 'Short films',
        'skits' => 'Skits',
        'training' => 'Training',
        'interviews' => 'Interviews',
        'behind_the_scenes' => 'Behind the scenes',
        'events' => 'Events',
        'testimonials' => 'Testimonials',
        'ministry_stories' => 'Ministry stories',
        'leadership_messages' => 'Leadership messages',
        'creative_tips' => 'Creative tips',
    ];

    protected $guarded = ['id', 'slug'];

    protected function casts(): array
    {
        return ['recorded_on' => 'date', 'published_at' => 'datetime', 'is_featured' => 'boolean', 'is_demo' => 'boolean'];
    }

    /** Accepts any common YouTube link (watch, youtu.be, shorts, live, embed) or a bare id. */
    public static function youtubeIdFrom(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~youtube(?:-nocookie)?\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/.*[?&]v=([A-Za-z0-9_-]{11})~',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }

    public function thumbnailUrl(string $size = 'hqdefault'): string
    {
        return 'https://i.ytimg.com/vi/'.$this->youtube_id.'/'.$size.'.jpg';
    }

    public function embedUrl(): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id.'?autoplay=1&rel=0&modestbranding=1';
    }

    public function youtubeUrl(): string
    {
        return 'https://www.youtube.com/watch?v='.$this->youtube_id;
    }
}
