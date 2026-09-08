<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
     protected $table = 'roles';

     protected $fillable = [
          'name',
          'label',
          'is_active',
     ];

     protected $casts = [
          'is_active' => 'boolean',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function rolePermissions()
     {
          return $this->hasMany(RolePermission::class);
     }

     public function roleMenus()
     {
          return $this->hasMany(RoleMenu::class);
     }

     public function menus()
     {
          return $this->belongsToMany(
               Menu::class,
               'role_menus'
          );
     }

     public function userRoles()
     {
          return $this->hasMany(
               UserRole::class,
               'role_id'
          );
     }

     public function users()
     {
          return $this->belongsToMany(
               User::class,
               'user_roles',
               'role_id',
               'user_id'
          )
          ->withPivot('is_system')
          ->withTimestamps();
     }

     public function permissions()
     {
          return $this->belongsToMany(
               Permission::class,
               'role_permissions',
               'role_id',
               'permission_id'
          )->withTimestamps();
     }

     /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */
     public function scopeActive($query)
     {
          return $query->where(
               'is_active',
               true
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */
     public function isActive(): bool
     {
          return $this->is_active;
     }

     public function activeUsers()
     {
          return $this->users()
               ->whereNull('users.deleted_at');
     }

     /*
     |--------------------------------------------------------------------------
     | Boot
     |--------------------------------------------------------------------------
     */
     protected static function booted(): void
     {
          static::saved(function () {
               Cache::forget('roles.map');
          });

          static::deleted(function () {
               Cache::forget('roles.map');
          });
     }
}