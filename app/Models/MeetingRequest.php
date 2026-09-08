<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingRequest extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING   = 'pending';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED   = 'expired';

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    */
    public const SOURCE_MATCHMAKING = 'matchmaking';
    public const SOURCE_DIRECT = 'direct';
    public const SOURCE_ADMIN = 'admin';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'ulid',

        'event_time_slot_id',

        'sender_participant_id',
        'receiver_participant_id',

        'meeting_mode',
        'source',

        'is_recommended',
        'recommendation_score',

        'message',

        'status',

        'expires_at',
        'accepted_at',
        'rejected_at',
        'cancelled_at',

        'created_by',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'event_time_slot_id' => 'integer',

        'sender_participant_id' => 'integer',
        'receiver_participant_id' => 'integer',

        'recommendation_score' => 'integer',

        'is_recommended' => 'boolean',

        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function getRouteKeyName(): string {
        return 'ulid';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function meeting(): HasOne
    {
        return $this->hasOne(Meeting::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(
            EventTimeSlot::class,
            'event_time_slot_id'
        );
    }

    public function senderParticipant(): BelongsTo
    {
        return $this->belongsTo(
            EventParticipant::class,
            'sender_participant_id'
        );
    }

    public function receiverParticipant(): BelongsTo
    {
        return $this->belongsTo(
            EventParticipant::class,
            'receiver_participant_id'
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

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    */
    public static function sources(): array
    {
        return [
            self::SOURCE_MATCHMAKING,
            self::SOURCE_DIRECT,
            self::SOURCE_ADMIN,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function statuses(): array
    {
        return [

            self::STATUS_PENDING,

            self::STATUS_ACCEPTED,

            self::STATUS_REJECTED,

            self::STATUS_CANCELLED,

            self::STATUS_EXPIRED,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        static::creating(function ($request) {

            if (empty($request->ulid)) {
                $request->ulid = (string) Str::ulid();
            }

        });
    }
}