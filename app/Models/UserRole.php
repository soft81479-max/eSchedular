<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model {
     protected $table = 'user_roles';

     protected $fillable = [
          'user_id', 'role_id', 'is_system',
     ];

     protected $casts = [

          'user_id'   => 'integer',
          'role_id'   => 'integer',

          'is_system' => 'boolean',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function user() {
          return $this->belongsTo(User::class, 'user_id');
     }

     public function role() {
          return $this->belongsTo(Role::class, 'role_id');
     }
}