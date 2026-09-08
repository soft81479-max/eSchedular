<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMatchRule extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'event_id',

        'source_event_participant_type_id',
        'target_event_participant_type_id',

        'priority_score',

        'is_match_allowed',
        'auto_recommend',
        'visibility_enabled',

        'created_by',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'event_id' => 'integer',

        'source_event_participant_type_id' => 'integer',
        'target_event_participant_type_id' => 'integer',

        'priority_score' => 'integer',

        'is_match_allowed' => 'boolean',
        'auto_recommend' => 'boolean',
        'visibility_enabled' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sourceParticipantType(): BelongsTo
    {
        return $this->belongsTo(
            EventParticipantType::class,
            'source_event_participant_type_id'
        );
    }

    public function targetParticipantType(): BelongsTo
    {
        return $this->belongsTo(
            EventParticipantType::class,
            'target_event_participant_type_id'
        );
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

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isMatchAllowed(): bool
    {
        return $this->is_match_allowed;
    }

    public function isAutoRecommend(): bool
    {
        return $this->auto_recommend;
    }

    public function isVisible(): bool
    {
        return $this->visibility_enabled;
    }
}