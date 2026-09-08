<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipantType extends Model
{
    protected $fillable = [
        'event_id',
        'participant_type_id',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'can_initiate_meetings',
        'can_receive_meetings',
        'priority_level',
        'max_daily_meetings',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'participant_type_id' => 'integer',

        'priority_level' => 'integer',
        'max_daily_meetings' => 'integer',

        'sort_order' => 'integer',

        'can_initiate_meetings' => 'boolean',
        'can_receive_meetings' => 'boolean',

        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participantType(): BelongsTo
    {
        return $this->belongsTo(ParticipantType::class);
    }

    public function eventOrganizations(): HasMany
    {
        return $this->hasMany(
            EventOrganization::class
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

    public function canInitiateMeetings(): bool
    {
        return $this->can_initiate_meetings;
    }

    public function canReceiveMeetings(): bool
    {
        return $this->can_receive_meetings;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where(
            'is_active',
            true
        );
    }
}