<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of an exam's composition, such as "10 moderate Acting questions". */
class ExamBlueprintRow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuestionCategory::class, 'question_category_id');
    }

    /** Approved questions this row can draw from. */
    public function pool()
    {
        return Question::approved()
            ->when($this->question_category_id, fn ($q) => $q->where('question_category_id', $this->question_category_id))
            ->when($this->difficulty, fn ($q) => $q->where('difficulty', $this->difficulty));
    }

    public function label(): string
    {
        return trim(($this->difficulty ? Question::DIFFICULTIES[$this->difficulty].' ' : '').($this->category?->name ?? 'Any category'));
    }
}
