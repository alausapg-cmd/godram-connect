<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A question in the GODRAM question bank.
 *
 * options/answer by type:
 *   single      options [{key,text}]            answer {key}
 *   multiple    options [{key,text}]            answer {keys: []}
 *   true_false  -                               answer {value: bool}
 *   short       -                               answer {accepted: []}
 *   fill_blank  stem contains ____              answer {accepted: []}
 *   matching    options [{key,left,right}]      each left matches its own right
 *   ordering    options [{key,text}] in order   the stored order is correct
 *   open        -                               marked by an examiner
 */
class Question extends Model
{
    public const TYPES = [
        'single' => 'Multiple choice (one answer)',
        'multiple' => 'Multiple response (several answers)',
        'true_false' => 'True or false',
        'short' => 'Short answer',
        'fill_blank' => 'Fill in the blank',
        'matching' => 'Matching',
        'ordering' => 'Put in order',
        'open' => 'Written answer (marked by an examiner)',
    ];

    public const DIFFICULTIES = ['easy' => 'Easy', 'moderate' => 'Moderate', 'difficult' => 'Difficult'];

    public const STATUSES = ['draft' => 'Waiting for review', 'approved' => 'Approved', 'retired' => 'Retired'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'version' => 1, 'difficulty' => 'moderate', 'shuffle_options' => true, 'marks' => 1];

    protected function casts(): array
    {
        return ['options' => 'array', 'answer' => 'array', 'marks' => 'float', 'shuffle_options' => 'boolean', 'reviewed_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function uses(): HasMany
    {
        return $this->hasMany(AttemptQuestion::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isAutoMarked(): bool
    {
        return $this->type !== 'open';
    }

    /**
     * The question as one candidate will see it. The answer stays in the
     * snapshot on the server and is removed before anything is sent to the browser.
     */
    public function snapshot(bool $shuffle): array
    {
        $options = $this->options ?? [];
        $shuffle = $shuffle && $this->shuffle_options;
        $presented = match ($this->type) {
            'single', 'multiple' => $shuffle ? collect($options)->shuffle()->values()->all() : $options,
            'ordering' => self::shuffleUntilChanged($options),
            'matching' => [
                'left' => collect($options)->map(fn ($p) => ['key' => $p['key'], 'text' => $p['left']])->all(),
                'right' => collect($options)->map(fn ($p) => ['key' => $p['key'], 'text' => $p['right']])->shuffle()->values()->all(),
            ],
            default => null,
        };

        return [
            'type' => $this->type,
            'version' => $this->version,
            'scenario' => $this->scenario,
            'stem' => $this->stem,
            'media_kind' => $this->media_kind,
            'media_path' => $this->media_path,
            'youtube_id' => $this->youtube_id,
            'options' => $presented,
            'answer' => $this->type === 'ordering' ? collect($options)->pluck('key')->all() : $this->answer,
            'marks' => (float) $this->marks,
            'explanation' => $this->explanation,
        ];
    }

    /** Never present an ordering question already in the right order. */
    protected static function shuffleUntilChanged(array $items): array
    {
        if (count($items) < 2) {
            return $items;
        }
        do {
            $shuffled = collect($items)->shuffle()->values()->all();
        } while (array_column($shuffled, 'key') === array_column($items, 'key'));

        return $shuffled;
    }

    /** @return array{0: ?bool, 1: ?float} [correct, marks] — null when it needs an examiner or was not answered. */
    public static function grade(array $snapshot, mixed $response): array
    {
        $marks = (float) $snapshot['marks'];
        if (self::isBlank($response)) {
            return [false, 0.0];
        }
        $answer = $snapshot['answer'] ?? [];

        switch ($snapshot['type']) {
            case 'single':
                $ok = (string) $response === (string) ($answer['key'] ?? '');
                break;
            case 'multiple':
                $given = collect((array) $response)->map(fn ($k) => (string) $k)->sort()->values()->all();
                $ok = $given === collect($answer['keys'] ?? [])->map(fn ($k) => (string) $k)->sort()->values()->all();
                break;
            case 'true_false':
                $ok = filter_var($response, FILTER_VALIDATE_BOOLEAN) === (bool) ($answer['value'] ?? false);
                break;
            case 'short':
            case 'fill_blank':
                $ok = in_array(self::normalise((string) $response), array_map([self::class, 'normalise'], $answer['accepted'] ?? []), true);
                break;
            case 'ordering':
                $ok = array_values(array_map('strval', (array) $response)) === array_map('strval', $answer);
                break;
            case 'matching':
                $pairs = $snapshot['options']['left'] ?? [];
                $right = (array) $response;
                $hits = collect($pairs)->filter(fn ($p) => (string) ($right[$p['key']] ?? '') === (string) $p['key'])->count();
                $fraction = count($pairs) ? $hits / count($pairs) : 0;

                return [$hits === count($pairs), round($marks * $fraction, 2)];
            case 'open':
                return [null, null];
            default:
                return [false, 0.0];
        }

        return [$ok, $ok ? $marks : 0.0];
    }

    public static function isBlank(mixed $response): bool
    {
        if ($response === null || $response === '') {
            return true;
        }
        if (is_array($response)) {
            return collect($response)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty();
        }

        return is_string($response) && trim($response) === '';
    }

    public static function normalise(string $text): string
    {
        return Str::of($text)->lower()->replaceMatches('/[^\p{L}\p{N}]+/u', ' ')->squish()->toString();
    }

    /** The correct answer in words, for review screens and the question bank. */
    public static function answerText(array $snapshot): string
    {
        $answer = $snapshot['answer'] ?? [];
        $options = $snapshot['options'] ?? [];
        $text = fn ($key) => collect(is_array($options) && isset($options[0]) ? $options : [])->firstWhere('key', $key)['text'] ?? $key;

        return match ($snapshot['type']) {
            'single' => $text($answer['key'] ?? ''),
            'multiple' => collect($answer['keys'] ?? [])->map($text)->join('; '),
            'true_false' => ($answer['value'] ?? false) ? 'True' : 'False',
            'short', 'fill_blank' => implode(' or ', $answer['accepted'] ?? []),
            'ordering' => collect($answer)->map($text)->join(' → '),
            'matching' => collect($options['left'] ?? [])->map(fn ($l) => $l['text'].' — '.(collect($options['right'] ?? [])->firstWhere('key', $l['key'])['text'] ?? ''))->join('; '),
            default => 'Marked by an examiner',
        };
    }
}
