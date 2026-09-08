<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationUser extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING  = 'pending';
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_BLOCKED  = 'blocked';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'ulid',

        'organization_id',
        'user_id',

        'designation',
        'department',
        'bio',

        'status',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'user_id' => 'integer',
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
    | Organization
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo {
        return $this->belongsTo(
            Organization::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    */
    public function user(): BelongsTo {
        return $this->belongsTo(
            User::class
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
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getDisplayNameAttribute(): string {
        return $this->user?->name ?? '';
    }

    public function getEmailAttribute(): ?string {
        return $this->user?->email;
    }

    public function getPhoneAttribute(): ?string {
        return $this->user?->phone;
    }

    public function getAvatarUrlAttribute(): string {
        return $this->user?->avatar_url ?? asset('images/defaults/avatar.png');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopePending($query) {
        return $query->where(
            'status',
            self::STATUS_PENDING
        );
    }

    public function scopeActive($query) {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeInactive($query) {
        return $query->where(
            'status',
            self::STATUS_INACTIVE
        );
    }

    public function scopeBlocked($query) {
        return $query->where(
            'status',
            self::STATUS_BLOCKED
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function statuses(): array {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_BLOCKED,
        ];
    }

    public function isPending(): bool {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isBlocked(): bool {
        return $this->status === self::STATUS_BLOCKED;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted() {
        static::creating(function ($organizationUser) {

            if (empty($organizationUser->ulid)) {
                $organizationUser->ulid = (string) Str::ulid();
            }

        });
    }
}