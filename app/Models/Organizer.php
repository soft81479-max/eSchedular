<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Organizer extends Model {
     use SoftDeletes;

     /*
     |--------------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------------
     */
     protected $table = 'organizers';

     /*
     |--------------------------------------------------------------------------
     | Statuses
     |--------------------------------------------------------------------------
     */
     public const STATUS_PENDING   = 'pending';
     public const STATUS_ACTIVE    = 'active';
     public const STATUS_INACTIVE  = 'inactive';
     public const STATUS_SUSPENDED = 'suspended';
     public const STATUS_REJECTED = 'rejected';

     /*
     |--------------------------------------------------------------------------
     | Fillable
     |--------------------------------------------------------------------------
     */
     protected $fillable = [
          'ulid',

          // Branding
          'logo',
          'banner_image',

          // Organization
          'name',
          'website',
          'email',
          'phone',
          'description',

          // Address
          'address_line_1',
          'address_line_2',
          'country_id',
          'state_id',
          'city_id',
          'pincode',

          // Compliance
          'gst_number',
          'pan_number',
          'registration_number',

          // Status
          'status',
          'is_verified',

          // Audit
          'created_by',
          'updated_by',
     ];

     /*
     |--------------------------------------------------------------------------
     | Casts
     |--------------------------------------------------------------------------
     */
     protected $casts = [
          'country_id' => 'string',
          'state_id' => 'integer',
          'city_id' => 'integer',

          'created_by' => 'integer',
          'updated_by' => 'integer',

          'is_verified' => 'boolean',

          'deleted_at' => 'datetime',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function organizations() {
          return $this->hasMany(
               Organization::class,
               'organizer_id'
          );
     }

     /**
     * Organizer team members.
     */
     public function users() {
          return $this->belongsToMany(
               User::class,
               'organizer_users'
          )
          ->withPivot([
               'is_primary',
               'status',
               'joined_at',
          ])
          ->withTimestamps();
     }

     /**
     * Organizer memberships.
     */
     public function organizerUsers() {
          return $this->hasMany(
               OrganizerUser::class,
               'organizer_id'
          );
     }

     /**
     * Organizer owner.
     */
     public function primaryMembership() {
          return $this->hasOne(
               OrganizerUser::class,
               'organizer_id'
          )->where(
               'is_primary',
               true
          );
     }

     /**
     * Organizer events.
     */
     public function events() {
          return $this->hasMany(
               Event::class,
               'organizer_id'
          );
     }

     /**
     * Country.
     */
     public function country() {
          return $this->belongsTo(
               Country::class,
               'country_id',
               'country_id'
          );
     }

     /**
     * State.
     */
     public function state() {
          return $this->belongsTo(
               State::class,
               'state_id',
               'state_id'
          );
     }

     /**
     * City.
     */
     public function city() {
          return $this->belongsTo(
               City::class,
               'city_id',
               'city_id'
          );
     }

     /**
     * Created By.
     */
     public function createdBy() {
          return $this->belongsTo(
               User::class,
               'created_by'
          );
     }

     /**
     * Updated By.
     */
     public function updatedBy() {
          return $this->belongsTo(
               User::class,
               'updated_by'
          );
     }

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
     | Scopes
     |--------------------------------------------------------------------------
     */
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

     public function scopePending($query) {
          return $query->where(
               'status',
               self::STATUS_PENDING
          );
     }

     public function scopeRejected($query) {
          return $query->where(
               'status',
               self::STATUS_PENDING
          );
     }

     public function scopeSuspended($query) {
          return $query->where(
               'status',
               self::STATUS_REJECTED
          );
     }

     public function scopeVerified($query) {
          return $query->where(
               'is_verified',
               true
          );
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

     public function getBannerImageUrlAttribute(): string {
          return ($this->banner_image && Storage::disk('public')->exists($this->banner_image))
               ? Storage::url($this->banner_image)
               : asset('images/defaults/banner.jpg');
     }

     public function getStatusColorAttribute(): string {
          return match ($this->status) {
               self::STATUS_ACTIVE    => 'success',
               self::STATUS_PENDING   => 'warning',
               self::STATUS_INACTIVE  => 'secondary',
               self::STATUS_SUSPENDED => 'danger',
               self::STATUS_REJECTED => 'danger',
               default                => 'light',
          };
     }

     public function getStatusLabelAttribute(): string {
          return match ($this->status) {
               self::STATUS_ACTIVE    => 'Active',
               self::STATUS_PENDING   => 'Pending',
               self::STATUS_INACTIVE  => 'Inactive',
               self::STATUS_SUSPENDED => 'Suspended',
               self::STATUS_REJECTED => 'Rejected',
               default                => ucfirst($this->status),
          };
     }

     /**
     * Primary organizer user.
     */
     public function getPrimaryUserAttribute() {
          return $this->primaryMembership?->user;
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */
     public function isActive(): bool {
          return $this->status === self::STATUS_ACTIVE;
     }

     public function isVerified(): bool {
          return $this->is_verified;
     }

     protected static function booted(): void {
          static::creating(function ($organizer) {
               if (empty($organizer->ulid)) {
                    $organizer->ulid = (string) \Illuminate\Support\Str::ulid();
               }
          });
     }

     public function isPending(): bool {
          return $this->status === self::STATUS_PENDING;
     }

     public function isInactive(): bool {
          return $this->status === self::STATUS_INACTIVE;
     }

     public function isSuspended(): bool {
          return $this->status === self::STATUS_SUSPENDED;
     }

     public function isRejected(): bool {
          return $this->status === self::STATUS_REJECTED;
     }

     public static function statuses(): array {
          return [
               self::STATUS_PENDING,
               self::STATUS_ACTIVE,
               self::STATUS_INACTIVE,
               self::STATUS_SUSPENDED,
               self::STATUS_REJECTED,
          ];
     }
}