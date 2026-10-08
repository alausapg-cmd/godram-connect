<?php

namespace App\Models;

use App\Models\Concerns\HasCover;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Story extends Model
{
    use HasCover, HasSlug;

    public const COVER_TYPE = 'story';

    public const TYPES = [
        'testimony' => 'Testimony',
        'impact' => 'Impact story',
        'production' => 'Production story',
        'member' => 'Member story',
        'behind_the_scenes' => 'Behind the scenes',
        'milestone' => 'Historical milestone',
        'creative_journey' => 'Creative journey',
        'evangelistic_impact' => 'Evangelistic impact',
        'leadership' => 'Leadership story',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'published' => 'Published',
        'rejected' => 'Returned',
        'archived' => 'Archived',
    ];

    protected $guarded = ['id', 'slug'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'published_at' => 'datetime', 'is_featured' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    /** The story text as safe HTML: simple Markdown, with any raw HTML removed. */
    public function bodyHtml(): string
    {
        return Str::markdown((string) $this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 200));
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->status === 'published') {
            return true;
        }
        if (! $user) {
            return false;
        }

        return $this->author_id === $user->id || app(\App\Services\Access::class)->can($user, 'stories.review');
    }
}
