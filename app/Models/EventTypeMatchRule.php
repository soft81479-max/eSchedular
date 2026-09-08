<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTypeMatchRule extends Model
{
    protected $fillable = [
        'event_type_id',
        'source_participant_type_id',
        'target_participant_type_id',
        'priority_score',
        'is_match_allowed',
        'auto_recommend',
        'visibility_enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'event_type_id' => 'integer',

        'source_participant_type_id' => 'integer',
        'target_participant_type_id' => 'integer',

        'priority_score' => 'integer',

        'is_match_allowed' => 'boolean',
        'auto_recommend' => 'boolean',
        'visibility_enabled' => 'boolean',
    ];

    public function eventType()
    {
        return $this->belongsTo(EventType::class);
    }

    public function sourceParticipantType()
    {
        return $this->belongsTo(ParticipantType::class, 'source_participant_type_id');
    }

    public function targetParticipantType()
    {
        return $this->belongsTo(ParticipantType::class, 'target_participant_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function allowsMatching(): bool
    {
        return $this->is_match_allowed;
    }

    public function autoRecommends(): bool
    {
        return $this->auto_recommend;
    }

    public function isVisible(): bool
    {
        return $this->visibility_enabled;
    }
}