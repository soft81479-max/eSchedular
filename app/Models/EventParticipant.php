<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventParticipant extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_REGISTERED = 'registered';
    const STATUS_CONFIRMED  = 'confirmed';
    const STATUS_CHECKED_IN = 'checked_in';
    const STATUS_INACTIVE   = 'inactive';
    const STATUS_CANCELLED  = 'cancelled';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'ulid',

        'is_primary',

        'event_organization_id',
        'organization_user_id',

        'badge_name',
        'badge_title',

        'qr_code',

        'matchmaking_enabled',

        'checked_in_at',

        'notes',

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
        'event_organization_id' => 'integer',
        'organization_user_id'  => 'integer',

        'is_primary'   => 'boolean',

        'matchmaking_enabled'   => 'boolean',

        'checked_in_at'         => 'datetime',
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
    | Event Organization
    |--------------------------------------------------------------------------
    */
    public function eventOrganization(): BelongsTo {
        return $this->belongsTo(EventOrganization::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Organization User
    |--------------------------------------------------------------------------
    */
    public function organizationUser(): BelongsTo {
        return $this->belongsTo(OrganizationUser::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Sent Meetings
    |--------------------------------------------------------------------------
    */
    public function sentMeetings(): HasMany {
        return $this->hasMany(
            Meeting::class,
            'sender_participant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Received Meetings
    |--------------------------------------------------------------------------
    */
    public function receivedMeetings(): HasMany {
        return $this->hasMany(
            Meeting::class,
            'receiver_participant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sent Meeting Requests
    |--------------------------------------------------------------------------
    */
    public function sentMeetingRequests(): HasMany {
        return $this->hasMany(
            MeetingRequest::class,
            'sender_participant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Received Meeting Requests
    |--------------------------------------------------------------------------
    */
    public function receivedMeetingRequests(): HasMany {
        return $this->hasMany(
            MeetingRequest::class,
            'receiver_participant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Availabilities
    |--------------------------------------------------------------------------
    */
    public function availabilities(): HasMany {
        return $this->hasMany(
            ParticipantAvailability::class
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

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getDisplayNameAttribute(): string {
        return $this->badge_name
            ?: $this->organizationUser?->display_name
            ?: '';
    }

    public function getAvatarUrlAttribute(): string {
        return $this->organizationUser?->avatar_url
            ?? asset('images/defaults/avatar.png');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeConfirmed($query) {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCheckedIn($query) {
        return $query->where('status', self::STATUS_CHECKED_IN);
    }

    public function scopeMatchmakingEnabled($query) {
        return $query->where('matchmaking_enabled', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function statuses(): array {
        return [
            self::STATUS_REGISTERED,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
            self::STATUS_INACTIVE,
            self::STATUS_CANCELLED,
        ];
    }

    public function isCheckedIn(): bool {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function isRegistered(): bool {
        return $this->status === self::STATUS_REGISTERED;
    }

    public function isInactive(): bool {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isCancelled(): bool {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPrimary(): bool {
        return $this->is_primary;
    }

    public function isSecondary(): bool {
        return ! $this->is_primary;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($participant) {

            if (empty($participant->ulid)) {
                $participant->ulid = (string) Str::ulid();
            }

        });
    }

    public function isConfirmed(): bool {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function scopeWorkspaceAccessible($query) {
        return $query->whereIn('status', [
            self::STATUS_REGISTERED,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Label
    |--------------------------------------------------------------------------
    */
    public function getStatusLabelAttribute(): string {
        return match ($this->status) {
            self::STATUS_REGISTERED => 'Registered',
            self::STATUS_CONFIRMED  => 'Confirmed',
            self::STATUS_CHECKED_IN => 'Checked In',
            self::STATUS_INACTIVE   => 'Inactive',
            self::STATUS_CANCELLED  => 'Cancelled',
            default                 => 'Unknown',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Status Badge Class
    |--------------------------------------------------------------------------
    */
    public function getStatusBadgeClassAttribute(): string {
        return match ($this->status) {
            self::STATUS_REGISTERED => 'bg-info',
            self::STATUS_CONFIRMED  => 'bg-success',
            self::STATUS_CHECKED_IN => 'bg-primary',
            self::STATUS_INACTIVE   => 'bg-secondary',
            self::STATUS_CANCELLED  => 'bg-danger',
            default                 => 'bg-light text-dark',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Active for Networking
    |--------------------------------------------------------------------------
    */
    public function scopeNetworking($query) {
        return $query->whereIn('status', [
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
        ]);
    }
}