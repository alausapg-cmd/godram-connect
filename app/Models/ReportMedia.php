<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportMedia extends Model
{
    protected $table = 'report_media';

    protected $guarded = ['id'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ActivityReport::class, 'activity_report_id');
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }
}
