<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasRoles;
use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Notifications\EventPasswordResetNotification;

class User extends Authenticatable {
    use Notifiable, SoftDeletes, HasRoles, HasPermissions;

    protected $table = 'users';

    protected $fillable = [
        'ulid','avatar','name','email','phone','password','timezone','status','remember_token','email_verified_at',
        'last_login_at','failed_attempts','locked_until','last_logout_at','last_login_ip','last_user_agent','device_fingerprint',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'      => 'datetime',
        'last_logout_at'     => 'datetime',
        'locked_until'       => 'datetime',
        'deleted_at'         => 'datetime',
    ];
    
    public function getRouteKeyName(): string {
        return 'ulid';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function userRoles() {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function roles() {
        return $this->belongsToMany(
            Role::class,'user_roles','user_id','role_id'
        )
        ->withPivot('is_system')
        ->withTimestamps();
    }

    public function userOverrides() {
        return $this->hasMany(
            UserOverride::class,
            'user_id'
        );
    }

    public function organizationUsers(): HasMany {
        return $this->hasMany(OrganizationUser::class);
    }

    public function ownedOrganizations(): HasMany {
        return $this->hasMany(
            Organization::class,
            'owner_user_id'
        );
    }

    public function createdOrganizations(): HasMany {
        return $this->hasMany(
            Organization::class,
            'created_by'
        );
    }

    public function updatedOrganizations(): HasMany {
        return $this->hasMany(
            Organization::class,
            'updated_by'
        );
    }

    public function approvedOrganizations(): HasMany {
        return $this->hasMany(
            Organization::class,
            'approved_by'
        );
    }

    public function eventParticipants(): HasManyThrough {
        return $this->hasManyThrough(
            EventParticipant::class,
            OrganizationUser::class,
            'user_id',
            'organization_user_id',
            'id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    | Avatar URL
    |--------------------------------------------------------------------------
    */
    public function getAvatarUrlAttribute(): string {
        return ($this->avatar && Storage::disk('public')->exists($this->avatar))
            ? Storage::url($this->avatar)
            : asset('images/defaults/avatar.png');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function roleName() {
        return $this->roles()
        ->orderBy('roles.id')
        ->first();
    }

    public function isActive(): bool {
        return $this->status === 'active';
    }

    public function getStatusColorAttribute(): string {
        return match ($this->status) {
            'active'   => 'success',
            'inactive' => 'secondary',
            'pending'  => 'warning',
            'locked'   => 'danger',
            'suspended'=> 'dark',
            default    => 'light',
        };
    }

    /**
     * Organizer memberships.
     */
    public function organizerUsers() {
        return $this->hasMany(
            OrganizerUser::class,
            'user_id'
        );
    }

    /**
     * Organizers.
     */
    public function organizers() {
        return $this->belongsToMany(
            Organizer::class,
            'organizer_users'
        )
        ->withPivot([
            'is_primary',
            'status',
            'joined_at',
        ])
        ->withTimestamps();
    }

    protected static function booted(): void {
        static::creating(function ($user) {
            if (empty($user->ulid)) {
                $user->ulid = (string) \Illuminate\Support\Str::ulid();
            }
        });
    }

    public function sendPasswordResetNotification($token): void {
        $url = route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ]);

        $this->notify(
            new EventPasswordResetNotification($url)
        );
    }
}