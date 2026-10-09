<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionCategory extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
