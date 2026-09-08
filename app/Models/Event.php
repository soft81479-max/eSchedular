<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Event extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ACTIVE    = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_ARCHIVED  = 'archived';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'ulid',

        'organizer_id',

        'event_type_id',
        'networking_mode_id',
        'matching_strategy_id',

        'name',
        'description',

        'logo',
        'banner_image',

        'start_date',
        'end_date',
        'booking_deadline',

        'timezone',

        'venue_name',
        'address',
        'city',
        'country',

        'default_slot_duration',
        'default_slot_capacity',

        'compatibility_engine_enabled',

        'allow_overlapping_slots',
        'allow_concurrent_meetings',
        'meeting_approval_required',

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
        'start_date'                     => 'date',
        'end_date'                       => 'date',
        'booking_deadline'               => 'datetime',

        'compatibility_engine_enabled'   => 'boolean',
        'allow_overlapping_slots'        => 'boolean',
        'allow_concurrent_meetings'      => 'boolean',
        'meeting_approval_required'      => 'boolean',
    ];

    public function getRouteKeyName(): string {
        return 'ulid';
    }

    /*
    |--------------------------------------------------------------------------
    | Organizer
    |--------------------------------------------------------------------------
    */
    public function organizer(): BelongsTo {
        return $this->belongsTo(Organizer::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */
    public function eventType(): BelongsTo {
        return $this->belongsTo(EventType::class);
    }

    public function networkingMode(): BelongsTo {
        return $this->belongsTo(NetworkingMode::class);
    }

    public function matchingStrategy(): BelongsTo {
        return $this->belongsTo(MatchingStrategy::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    */
    public function participantTypes(): HasMany {
        return $this->hasMany(EventParticipantType::class);
    }

    public function matchRules(): HasMany {
        return $this->hasMany(
            EventMatchRule::class
        )
        ->with([
            'sourceParticipantType',
            'targetParticipantType',
        ])
        ->orderByDesc('priority_score')
        ->orderBy('source_event_participant_type_id')
        ->orderBy('target_event_participant_type_id');
    }

    public function schedules(): HasMany {
        return $this->hasMany(EventSchedule::class);
    }

    public function organizations(): HasMany {
        return $this->hasMany(EventOrganization::class);
    }

    public function participants(): HasManyThrough {
        return $this->hasManyThrough(
            EventParticipant::class,
            EventOrganization::class,

            'event_id',               // FK on event_organizations
            'event_organization_id',  // FK on event_participants

            'id',                     // Local key on events
            'id'                      // Local key on event_organizations
        );
    }

    public function booths(): HasMany {
        return $this->hasMany(EventBooth::class);
    }

    public function sessions(): HasMany {
        return $this->hasMany(EventSession::class);
    }

    public function tracks(): HasMany {
        return $this->hasMany(EventTrack::class);
    }

    public function timeSlots(): HasManyThrough {
        return $this->hasManyThrough(
            EventTimeSlot::class,
            EventSchedule::class,
            'event_id',      // Foreign key on event_schedules
            'event_schedule_id',   // Foreign key on event_time_slots
            'id',            // Local key on events
            'id'             // Local key on event_schedules
        );
    }

    public function participantTypeMatchRules(): HasMany {
        return $this->hasMany(EventMatchRule::class);
    }

    public function meetingRequests(): HasManyThrough {
        return $this->hasManyThrough(
            MeetingRequest::class,
            EventTimeSlot::class,
            'event_id',
            'event_time_slot_id',
            'id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted() {
        static::creating(function ($event) {

            if (empty($event->ulid)) {
                $event->ulid = (string) Str::ulid();
            }

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Logo URL
    |--------------------------------------------------------------------------
    */
    public function getLogoUrlAttribute(): string {
        return ($this->logo && Storage::disk('public')->exists($this->logo))
            ? Storage::url($this->logo)
            : asset('images/defaults/logo.png');
    }
    
    /*
    |--------------------------------------------------------------------------
    | Accessor : Banner URL
    |--------------------------------------------------------------------------
    */
    public function getBannerUrlAttribute() {
        return ($this->banner_image && Storage::disk('public')->exists($this->banner_image))
            ? Storage::url($this->banner_image)
            : asset('images/defaults/banner.jpg');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Duration
    |--------------------------------------------------------------------------
    */
    public function getDurationAttribute(): string {
        $days = $this->start_date->diffInDays($this->end_date) + 1;

        return $days === 1
            ? 'One Day Event'
            : "{$days} Days Event";
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Status Badge
    |--------------------------------------------------------------------------
    */
    public function getStatusBadgeAttribute(): string {
        return match ($this->status) {
            self::STATUS_DRAFT => '<span class="badge fs-xs bg-secondary">Draft</span>',
            self::STATUS_PUBLISHED => '<span class="badge fs-xs bg-primary">Published</span>',
            self::STATUS_ACTIVE => '<span class="badge fs-xs bg-success">Active</span>',
            self::STATUS_COMPLETED => '<span class="badge fs-xs bg-dark">Completed</span>',
            self::STATUS_CANCELLED => '<span class="badge fs-xs bg-danger">Cancelled</span>',
            self::STATUS_ARCHIVED => '<span class="badge fs-xs bg-warning text-dark">Archived</span>',
            default => '<span class="badge fs-xs bg-light border text-dark">Unknown</span>',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Networking Badge
    |--------------------------------------------------------------------------
    */
    public function getNetworkingBadgeAttribute(): string {
        if (! $this->networkingMode) {
            return '<span class="badge fs-xs bg-light text-dark">N/A</span>';
        }

        return match ($this->networkingMode->slug) {
            'open' => '<span class="badge fs-xs bg-primary">Open</span>',
            'scheduled' => '<span class="badge fs-xs bg-success">Scheduled</span>',
            'hybrid' => '<span class="badge fs-xs bg-warning text-dark">Hybrid</span>',
            default => 
                '<span class="badge fs-xs bg-secondary">'
                    . e($this->networkingMode->name) .
                '</span>',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Compatibility Badge
    |--------------------------------------------------------------------------
    */
    public function getCompatibilityBadgeAttribute(): string {
        return $this->compatibility_engine_enabled
            ? '<span class="badge fs-xs bg-success">AI ON</span>'
            : '<span class="badge fs-xs bg-danger">AI OFF</span>';
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Matching Strategy Badge
    |--------------------------------------------------------------------------
    */
    public function getMatchingStrategyBadgeAttribute(): string {
        if (! $this->matchingStrategy) {
            return '<span class="badge fs-xs bg-light text-dark">N/A</span>';
        }

        return '<span class="badge fs-xs bg-light text-dark border">'
            . e($this->matchingStrategy->name)
            . '</span>';
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Booking Deadline
    |--------------------------------------------------------------------------
    */
    public function getBookingDeadlineFormattedAttribute(): string {
        return $this->booking_deadline
            ? $this->booking_deadline->format('d M Y h:i A')
            : '-';
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Event Date Range
    |--------------------------------------------------------------------------
    */
    public function getDateRangeAttribute(): string {
        return $this->start_date->format('d M Y')
            .' - '.
            $this->end_date->format('d M Y');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Venue
    |--------------------------------------------------------------------------
    */
    public function getVenueAttribute(): string {
        return collect([
            $this->venue_name,
            $this->city,
            $this->country,
        ])
        ->filter()
        ->implode(', ');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Slot Summary
    |--------------------------------------------------------------------------
    */
    public function getSlotSummaryAttribute(): string {
        return "{$this->default_slot_duration} Min / Capacity {$this->default_slot_capacity}";
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor : Status Color
    |--------------------------------------------------------------------------
    */
    public function getStatusColorAttribute(): string {
        return match ($this->status) {
            self::STATUS_DRAFT      => 'secondary',
            self::STATUS_PUBLISHED  => 'primary',
            self::STATUS_ACTIVE     => 'success',
            self::STATUS_COMPLETED  => 'dark',
            self::STATUS_CANCELLED  => 'danger',
            self::STATUS_ARCHIVED   => 'warning',
            default                 => 'light',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */
    public static function activeStatuses(): array {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_ACTIVE,
        ];
    }

    public static function representativeAccessibleStatuses(): array {
        return [
            self::STATUS_PUBLISHED,
            self::STATUS_ACTIVE,
            self::STATUS_COMPLETED,
        ];
    }

    public static function editableStatuses(): array {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
        ];
    }

    public function isEditable(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublishable(): bool {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isDeletable(): bool {
        return
            $this->status === self::STATUS_DRAFT && $this->organizations()->count() == 0 && $this->participants()->count() == 0;
    }

    public function isArchivable(): bool {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCancellable(): bool {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_ACTIVE,
        ]);
    }
}   