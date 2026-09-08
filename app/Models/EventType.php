<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventType extends Model {
    protected $table = 'event_types';

    protected $fillable = [
        'name',
        'slug',
        'description',

        'icon',
        'color',

        'networking_mode_id', 
        'matching_strategy_id', 
        'compatibility_engine_enabled', 

        'allow_overlapping_slots',
        'allow_concurrent_meetings',
        'meeting_approval_required',

        'default_slot_duration',
        'default_slot_capacity',

        'is_active',
        'sort_order',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'networking_mode_id'            => 'integer',
        'matching_strategy_id'          => 'integer',

        'compatibility_engine_enabled'  => 'boolean',
        'allow_overlapping_slots'       => 'boolean',
        'allow_concurrent_meetings'     => 'boolean',
        'meeting_approval_required'     => 'boolean',

        'default_slot_duration'         => 'integer',
        'default_slot_capacity'         => 'integer',

        'is_active'                     => 'boolean',
        'sort_order'                    => 'integer',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function creator() {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater() {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function scopeActive($query) {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeOrdered($query) {
        return $query->orderBy(
            'sort_order'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getIsEnabledAttribute(): bool {
        return $this->is_active;
    }

    public function getStatusBadgeClassAttribute(): string {
        return $this->is_active ? 'bg-success' : 'bg-danger';
    }

    public function networkingMode() {
        return $this->belongsTo(NetworkingMode::class);
    }

    public function matchingStrategy() {
        return $this->belongsTo(MatchingStrategy::class);
    }

    public function participantTypes() {
        return $this->belongsToMany(
            ParticipantType::class,
            'event_type_participant_types'
        )->withPivot('sort_order')
        ->orderBy('event_type_participant_types.sort_order');
    }

    public function matchRules() {
        return $this->hasMany(EventTypeMatchRule::class)
            ->with([
                'sourceParticipantType:id,name',
                'targetParticipantType:id,name',
            ]);
    }
}