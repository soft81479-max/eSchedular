<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model {
     protected $table = 'modules';

     protected $fillable = [
          'name',
          'slug',
          'label',
          'is_active',
     ];

     protected $casts = [
          'is_active' => 'boolean',
     ];

     public function permissions() {
          return $this->hasMany(
               Permission::class,
               'module_id'
          );
     }

     public function scopeActive($query) {
          return $query->where(
               'is_active',
               true
          );
     }
}