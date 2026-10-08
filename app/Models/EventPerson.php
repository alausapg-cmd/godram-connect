<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPerson extends Model
{
    protected $table = 'event_people';

    public $timestamps = false;

    protected $guarded = ['id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function roleLabel(): string
    {
        return Event::ROLES[$this->role] ?? ucfirst($this->role);
    }
}
