<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_PUBLISHED  = 'published';
    public const STATUS_CANCELLED  = 'cancelled';

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_CANCELLED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [

        'ulid',

        'event_id',
        'event_track_id',
        'event_time_slot_id',

        'title',
        'slug',
        'description',

        'color',

        'sort_order',

        'status',

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

        'event_track_id' => 'integer',

        'event_time_slot_id' => 'integer',

        'sort_order' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function event(): BelongsTo     {
        return $this->belongsTo(
            Event::class
        );
    }

    public function track(): BelongsTo {
        return $this->belongsTo(
            EventTrack::class,
            'event_track_id'
        );
    }

    public function timeSlot(): BelongsTo {
        return $this->belongsTo(
            EventTimeSlot::class,
            'event_time_slot_id'
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
    public function scopePublished($query) {
        return $query->where(
            'status',
            self::STATUS_PUBLISHED
        );
    }

    public function scopeDraft($query) {
        return $query->where(
            'status',
            self::STATUS_DRAFT
        );
    }

    public function scopeCancelled($query) {
        return $query->where(
            'status',
            self::STATUS_CANCELLED
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function statuses(): array {
        return self::STATUSES;
    }

    public function isDraft(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCancelled(): bool {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasTrack(): bool {
        return ! is_null($this->event_track_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getDurationAttribute(): ?int {
        return $this->timeSlot?->duration;
    }

    public function getStatusLabelAttribute(): string {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Draft',
        };
    }

    public function getStatusBadgeClassAttribute(): string {
        return match ($this->status) {
            self::STATUS_PUBLISHED => 'bg-success',
            self::STATUS_CANCELLED => 'bg-danger',
            default => 'bg-warning',
        };
    }

    public function canBeDeleted(): bool {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($session) {

            if (empty($session->ulid)) {
                $session->ulid = (string) Str::ulid();
            }

            if (empty($session->slug)) {
                $session->slug = Str::slug($session->title);
            }

        });
    }
}