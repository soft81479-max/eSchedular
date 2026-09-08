<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantAvailability extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_AVAILABLE   = 'available';
    const STATUS_PREFERRED   = 'preferred';
    const STATUS_UNAVAILABLE = 'unavailable';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'ulid',

        'event_participant_id',
        'event_time_slot_id',

        'status',

        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'event_participant_id' => 'integer',
        'event_time_slot_id'   => 'integer',
    ];
    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function participant(): BelongsTo
    {
        return $this->belongsTo(
            EventParticipant::class,
            'event_participant_id'
        );
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(
            EventTimeSlot::class,
            'event_time_slot_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

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
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeAvailable($query)
    {
        return $query->where(
            'status',
            self::STATUS_AVAILABLE
        );
    }

    public function scopePreferred($query)
    {
        return $query->where(
            'status',
            self::STATUS_PREFERRED
        );
    }

    public function scopeUnavailable($query)
    {
        return $query->where(
            'status',
            self::STATUS_UNAVAILABLE
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function statuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_PREFERRED,
            self::STATUS_UNAVAILABLE,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Meeting Eligible Statuses
    |--------------------------------------------------------------------------
    */
    public static function meetingEligibleStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_PREFERRED,
        ];
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isPreferred(): bool
    {
        return $this->status === self::STATUS_PREFERRED;
    }

    public function isUnavailable(): bool
    {
        return $this->status === self::STATUS_UNAVAILABLE;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function ($availability) {

            if (empty($availability->ulid)) {
                $availability->ulid = (string) Str::ulid();
            }

        });
    }

    public function isMeetingEligible(): bool {
        return in_array(
            $this->status,
            self::meetingEligibleStatuses()
        );
    }
}