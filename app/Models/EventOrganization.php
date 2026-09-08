<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventOrganization extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Networking Access Levels
    |--------------------------------------------------------------------------
    */

    const ACCESS_FULL        = 'full';
    const ACCESS_RESTRICTED  = 'restricted';
    const ACCESS_VIEW_ONLY   = 'view_only';

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    */

    const VISIBILITY_PUBLIC  = 'public';
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_HIDDEN  = 'hidden';

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_INVITED = 'invited';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_WITHDRAWN = 'withdrawn';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [

        'ulid',

        'event_id',
        'organization_id',
        'event_participant_type_id',

        'matchmaking_enabled',
        'networking_access_level',

        'meeting_limit',
        'auto_accept_meetings',

        'visibility_status',

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
        'organization_id' => 'integer',
        'event_participant_type_id' => 'integer',

        'meeting_limit' => 'integer',

        'matchmaking_enabled' => 'boolean',
        'auto_accept_meetings' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Route Binding
    |--------------------------------------------------------------------------
    */
     public function getRouteKeyName(): string {
          return 'ulid';
     }

    /*
    |--------------------------------------------------------------------------
    | Event
    |--------------------------------------------------------------------------
    */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Participant Type
    |--------------------------------------------------------------------------
    */
    public function participantType(): BelongsTo {
        return $this->belongsTo(
            EventParticipantType::class,
            'event_participant_type_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event Participants
    |--------------------------------------------------------------------------
    */
    public function participants(): HasMany {
        return $this->hasMany(
            EventParticipant::class
        );
    }

    public function booth(): HasOne {
        return $this->hasOne(EventBooth::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Confirmed Participants
    |--------------------------------------------------------------------------
    */
    public function confirmedParticipants(): HasMany {
        return $this->participants()
            ->confirmed();
    }

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */
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
    public function scopeAccepted($query) {
        return $query->where(
            'status',
            self::STATUS_ACCEPTED
        );
    }

    public function scopeMatchmakingEnabled($query) {
        return $query->where(
            'matchmaking_enabled',
            true
        );
    }

    public function getStatusLabelAttribute() {
        return self::statuses()[
            $this->status
        ] ?? 'Unknown';
    }

    public function getStatusBadgeClassAttribute() {
        return match ($this->status) {
            self::STATUS_CONFIRMED => 'bg-success',
            self::STATUS_REJECTED => 'bg-danger',
            self::STATUS_WITHDRAWN => 'bg-warning',
            default => 'bg-warning',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function statuses() {
        return [
            self::STATUS_INVITED => 'invited',
            self::STATUS_CONFIRMED => 'confirmed',
            self::STATUS_REJECTED => 'rejected',
            self::STATUS_WITHDRAWN => 'withdrawn',
        ];
    }

    public static function accessLevels(): array {
        return [
            self::ACCESS_FULL,
            self::ACCESS_RESTRICTED,
            self::ACCESS_VIEW_ONLY,
        ];
    }

    public static function visibilityStatuses(): array {
        return [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_PRIVATE,
            self::VISIBILITY_HIDDEN,
        ];
    }

    public function scopeConfirmed($query) {
        return $query->where(
            'status',
            self::STATUS_CONFIRMED
        );
    }

    public function isMatchmakingEnabled(): bool {
        return $this->matchmaking_enabled;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($eventOrganization) {

            if (empty($eventOrganization->ulid)) {
                $eventOrganization->ulid = (string) Str::ulid();
            }

        });
    }

    public function isPublic(): bool {
        return $this->visibility_status === self::VISIBILITY_PUBLIC;
    }

    public function isPrivate(): bool {
        return $this->visibility_status === self::VISIBILITY_PRIVATE;
    }

    public function isHidden(): bool {
        return $this->visibility_status === self::VISIBILITY_HIDDEN;
    }
}