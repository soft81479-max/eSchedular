<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends Model {
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    const STATUS_PENDING   = 'pending';
    const STATUS_ACTIVE    = 'active';
    const STATUS_INACTIVE  = 'inactive';
    const STATUS_REJECTED  = 'rejected';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'ulid',
        'organizer_id',

        'name',
        

        'organization_type_id',
        'owner_user_id',

        'logo',

        'website',
        'email',
        'phone',

        'address',

        'country_id',
        'state_id',
        'city_id',

        'description',

        'status',
        'is_verified',

        'approved_at',
        'approved_by',

        'created_by',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'organizer_id' => 'integer',
        'organization_type_id' => 'integer',
        'owner_user_id' => 'integer',

        'country_id' => 'string',
        'state_id' => 'integer',
        'city_id' => 'integer',

        'is_verified'          => 'boolean',

        'approved_by' => 'integer',

        'approved_at' => 'datetime',
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
    | Organization Type
    |--------------------------------------------------------------------------
    */
    public function organizer(): BelongsTo {
        return $this->belongsTo(
            Organizer::class,
            'organizer_id'
        );
    }

    public function organizationType(): BelongsTo {
        return $this->belongsTo(OrganizationType::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Participants
    |--------------------------------------------------------------------------
    */
    public function participants(): HasManyThrough {
        return $this->hasManyThrough(
            EventParticipant::class,
            OrganizationUser::class,
            'organization_id',
            'organization_user_id',
            'id',
            'id'
        );
    }

    public function primaryParticipants(): HasManyThrough {
        return $this->participants()
            ->where('is_primary', true);
    }

    public function secondaryParticipants(): HasManyThrough {
        return $this->participants()
            ->where('is_primary', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Owner
    |--------------------------------------------------------------------------
    */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Location
    |--------------------------------------------------------------------------
    */
    public function country(): BelongsTo {
        return $this->belongsTo(
            Country::class,
            'country_id',
            'country_id'
        );
    }

    public function state(): BelongsTo {
        return $this->belongsTo(
            State::class,
            'state_id',
            'state_id'
        );
    }

    public function city(): BelongsTo {
        return $this->belongsTo(
            City::class,
            'city_id',
            'city_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Organization Users
    |--------------------------------------------------------------------------
    */
    public function organizationUsers(): HasMany {
        return $this->hasMany(OrganizationUser::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Organizations
    |--------------------------------------------------------------------------
    */
    public function eventOrganizations(): HasMany {
        return $this->hasMany(EventOrganization::class);
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

    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pending(): bool {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInactive(): bool {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isRejected(): bool {
        return $this->status === self::STATUS_REJECTED;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getLogoUrlAttribute(): string {
        return ($this->logo && Storage::disk('public')->exists($this->logo))
            ? Storage::url($this->logo)
            : asset('images/defaults/logo.png');
    }

    public function getLocationAttribute(): string {
        return collect([
            $this->city?->name,
            $this->state?->name,
            $this->country?->name,
        ])->filter()->implode(', ');
    }

    public function getFullAddressAttribute(): string {
        return collect([
            $this->address,
            $this->city?->name,
            $this->state?->name,
            $this->country?->name,
        ])->filter()->implode(', ');
    }

    public function getStatusColorAttribute(): string {
        return match ($this->status) {
            self::STATUS_ACTIVE    => 'success',
            self::STATUS_PENDING   => 'warning',
            self::STATUS_INACTIVE  => 'secondary',
            self::STATUS_REJECTED  => 'dark',
            default                => 'light',
        };
    }

    public function getStatusLabelAttribute(): string {
        return match ($this->status) {
            self::STATUS_ACTIVE    => 'Active',
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_INACTIVE  => 'Inactive',
            self::STATUS_REJECTED  => 'Rejected',
            default                => ucfirst($this->status),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopePending($query) {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query) {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeApproved($query) {
        return $query->whereNotNull('approved_at');
    }

    public function scopeVerified($query) {
        return $query->where('is_verified', true);
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
            self::STATUS_REJECTED,
        ];
    }

    public function isPending(): bool {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isVerified(): bool {
        return $this->is_verified;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($organization) {

            if (empty($organization->ulid)) {
                $organization->ulid = (string) Str::ulid();
            }

        });
    }
}