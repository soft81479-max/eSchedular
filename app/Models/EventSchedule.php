<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSchedule extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    */

    public const VISIBILITY_PUBLIC  = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_PRIVATE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [

        'ulid',

        'event_id',

        'title',
        'description',

        'schedule_date',

        'start_time',
        'end_time',

        'timezone',

        'type',
        'color',

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
        'event_id' => 'integer',

        'sort_order' => 'integer',

        'schedule_date' => 'date',

        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
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
    public function event(): BelongsTo {
        return $this->belongsTo(
            Event::class
        );
    }

    public function timeSlots(): HasMany {
        return $this->hasMany(
            EventTimeSlot::class,
            'event_schedule_id'
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

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopePublic($query) {
        return $query->where(
            'visibility',
            self::VISIBILITY_PUBLIC
        );
    }

    public function scopePrivate($query) {
        return $query->where(
            'visibility',
            self::VISIBILITY_PRIVATE
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function visibilities(): array {
        return self::VISIBILITIES;
    }

    public function isPublic(): bool {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isPrivate(): bool {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    public function hasTimeSlots(): bool {
        return $this->timeSlots()->exists();
    }

    public function canBeDeleted(): bool {
        return ! $this->timeSlots()->whereHas('meetingRequests')->exists()
            && ! $this->timeSlots()->whereHas('availabilities')->exists()
            && ! $this->timeSlots()->whereHas('sessionSlots')->exists()
            && ! $this->timeSlots()->whereHas('meetings')->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getFormattedDateAttribute(): string {
        return $this->schedule_date?->format('d M Y');
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($schedule) {
            if (empty($schedule->ulid)) {
                $schedule->ulid = (string) Str::ulid();
            }
        });
    }
}