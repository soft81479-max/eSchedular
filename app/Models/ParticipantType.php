<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParticipantType extends Model {
    protected $table = 'participant_types';

    protected $fillable = [
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
        'is_active' => 'boolean',

        'can_initiate_meetings' => 'boolean',
        'can_receive_meetings' => 'boolean',

        'priority_level' => 'integer',
        'max_daily_meetings' => 'integer',

        'sort_order' => 'integer',
    ];

    public function eventParticipantTypes(): HasMany
    {
        return $this->hasMany(
            EventParticipantType::class
        );
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query) {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeCanInitiateMeetings($query) {
        return $query->where(
            'can_initiate_meetings',
            true
        );
    }

    public function scopeCanReceiveMeetings($query) {
        return $query->where(
            'can_receive_meetings',
            true
        );
    }

    public function scopeOrdered($query) {
        return $query->orderBy(
            'sort_order'
        );
    }

    public function getIsEnabledAttribute(): bool {
        return $this->is_active;
    }

    public function getStatusBadgeClassAttribute(): string {
        return $this->is_active ? 'bg-success' : 'bg-danger';
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}