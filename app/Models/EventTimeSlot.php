<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventTimeSlot extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Slot Types
    |--------------------------------------------------------------------------
    */
    
    public const TYPE_REGISTRATION = 'registration';
    public const TYPE_MEETING      = 'meeting';
    public const TYPE_SESSION      = 'session';
    public const TYPE_BREAK        = 'break';
    public const TYPE_LUNCH        = 'lunch';
    public const TYPE_DINNER       = 'dinner';
    public const TYPE_BLOCKED      = 'blocked';
    public const TYPE_CUSTOM       = 'custom';
    public const TYPE_OTHER        = 'other';

    /*
    |--------------------------------------------------------------------------
    | Slot Modes
    |--------------------------------------------------------------------------
    */

    public const MODE_NETWORKING   = 'networking';
    public const MODE_PRESENTATION = 'presentation';
    public const MODE_PRIVATE      = 'private';
    public const MODE_FREE         = 'free';

    /*
    |--------------------------------------------------------------------------
    | Slot Visibility
    |--------------------------------------------------------------------------
    */

    public const VISIBILITY_PUBLIC   = 'public';
    public const VISIBILITY_PRIVATE  = 'private';
    public const VISIBILITY_INTERNAL = 'internal';

    /*
    |--------------------------------------------------------------------------
    | Slot Configuration
    |--------------------------------------------------------------------------
    */
    public const SLOT_TYPES = [
        self::TYPE_REGISTRATION,
        self::TYPE_MEETING,
        self::TYPE_SESSION,
        self::TYPE_BREAK,
        self::TYPE_LUNCH,
        self::TYPE_DINNER,
        self::TYPE_BLOCKED,
        self::TYPE_CUSTOM,
        self::TYPE_OTHER,
    ];

    public const SLOT_DURATIONS = [
        20,
        30,
        40,
        45,
        60,
    ];

    public const SLOT_GAPS = [
        5,
        10,
        15,
    ];

    public const SLOT_MODES = [
        self::MODE_NETWORKING,
        self::MODE_PRESENTATION,
        self::MODE_PRIVATE,
        self::MODE_FREE,
    ];

    public const SLOT_VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_INTERNAL,
    ];

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'ulid',

        'event_schedule_id',

        'start_at',
        'end_at',

        'capacity',

        'type',
        'mode',

        'title',
        'description',
        'color',

        'is_bookable',
        'visibility',

        'sort_order',

        'created_by',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'event_schedule_id' => 'integer',

        'capacity' => 'integer',

        'sort_order' => 'integer',

        'start_at' => 'datetime',
        'end_at'   => 'datetime',

        'is_bookable' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Route Key
    |--------------------------------------------------------------------------
    */
    public function getRouteKeyName(): string {
        return 'ulid';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function schedule(): BelongsTo {
        return $this->belongsTo(
            EventSchedule::class,
            'event_schedule_id'
        );
    }

    public function availabilities(): HasMany {
        return $this->hasMany(
            ParticipantAvailability::class
        );
    }

    public function meetings(): HasMany {
        return $this->hasMany(
            Meeting::class
        );
    }

    public function meetingRequests(): HasMany {
        return $this->hasMany(
            MeetingRequest::class
        );
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function session(): HasOne {
        return $this->hasOne(
            EventSession::class,
            'event_time_slot_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function types(): array {
        return self::SLOT_TYPES;
    }

    public static function durations(): array {
        return self::SLOT_DURATIONS;
    }

    public static function gaps(): array {
        return self::SLOT_GAPS;
    }

    public static function modes(): array {
        return self::SLOT_MODES;
    }

    public static function visibilities(): array {
        return self::SLOT_VISIBILITIES;
    }

    /*
    |--------------------------------------------------------------------------
    | Boolean Helpers
    |--------------------------------------------------------------------------
    */
    public function isMeeting(): bool {
        return $this->type === self::TYPE_MEETING;
    }

    public function isSession(): bool {
        return $this->type === self::TYPE_SESSION;
    }

    public function canHaveSession(): bool {
        return $this->type === self::TYPE_SESSION;
    }

    public function hasSession(): bool {
        return $this->session()->exists();
    }

    public function isBreak(): bool {
        return in_array(
            $this->type,
            [
                self::TYPE_BREAK,
                self::TYPE_LUNCH,
                self::TYPE_DINNER,
            ]
        );
    }

    public function isBookable(): bool {
        return $this->is_bookable;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getDurationAttribute(): int {
        return $this->start_at->diffInMinutes(
            $this->end_at
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($slot) {

            if (empty($slot->ulid)) {
                $slot->ulid = (string) Str::ulid();
            }

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getOtherParticipantAttribute() {
        $participant = auth()->user()
            ->organizationUser
            ?->eventParticipant;

        if (! $participant) {
            return null;
        }

        return $this->otherParticipant($participant);
    }
}