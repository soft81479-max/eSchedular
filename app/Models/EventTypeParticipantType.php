<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTypeParticipantType extends Model
{
    protected $fillable = [
        'event_type_id',
        'participant_type_id',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'event_type_id' => 'integer',
        'participant_type_id' => 'integer',

        'sort_order' => 'integer',
    ];

    public function eventType()
    {
        return $this->belongsTo(EventType::class);
    }

    public function eventTypeParticipantTypes(): HasMany
    {
        return $this->hasMany(
            EventTypeParticipantType::class
        );
    }
}