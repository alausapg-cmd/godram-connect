<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Call-and-response: a quick question, poll or "complete the statement" inside a lesson or live class. */
class Prompt extends Model
{
    public const TYPES = [
        'choice' => 'Multiple choice',
        'true_false' => 'True or false',
        'complete' => 'Complete the statement',
        'short' => 'Short answer',
        'poll' => 'Poll',
        'open' => 'Open response',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_live' => 'boolean', 'show_results' => 'boolean'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PromptResponse::class);
    }

    public function choices(): array
    {
        return $this->type === 'true_false' ? ['True', 'False'] : array_values(array_filter((array) $this->options));
    }

    public function hasChoices(): bool
    {
        return in_array($this->type, ['choice', 'true_false', 'poll']);
    }

    /** Polls and open responses have no right answer. */
    public function isMarked(): bool
    {
        return filled($this->answer) && ! in_array($this->type, ['poll', 'open']);
    }

    public function check(string $response): ?bool
    {
        if (! $this->isMarked()) {
            return null;
        }
        $clean = fn ($s) => Str::of($s)->lower()->replaceMatches('/[^\p{L}\p{N} ]/u', '')->squish()->toString();
        $accepted = array_map($clean, explode('|', $this->answer));

        return in_array($clean($response), $accepted, true);
    }

    /** Counts per choice, for polls and the facilitator's view. */
    public function tally(): array
    {
        $counts = $this->responses()->selectRaw('response, count(*) as n')->groupBy('response')->pluck('n', 'response');

        return collect($this->choices())->mapWithKeys(fn ($c) => [$c => (int) ($counts[$c] ?? 0)])->all();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
