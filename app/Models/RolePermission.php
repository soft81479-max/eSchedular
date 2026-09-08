<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model {
     protected $table = 'role_permissions';

     protected $fillable = [
          'role_id',
          'permission_id',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function role() {
          return $this->belongsTo(
               Role::class,
               'role_id'
          );
     }

     public function permission() {
          return $this->belongsTo(
               Permission::class,
               'permission_id'
          );
     }
}