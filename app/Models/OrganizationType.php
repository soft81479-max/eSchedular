<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationType extends Model {
     protected $table = 'organization_types';

     protected $fillable = [
          'name',
          'slug',
          'description',

          'icon',
          'color',

          'is_active',
          'sort_order',

          'created_by',
          'updated_by',
     ];

     protected $casts = [
          'is_active' => 'boolean',
          'sort_order' => 'integer',

          'created_at' => 'datetime',
          'updated_at' => 'datetime',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function organizations() {
          return $this->hasMany(
               Organization::class,
               'organization_type_id'
          );
     }

     public function creator(): BelongsTo {
          return $this->belongsTo(User::class, 'created_by');
     }

     public function updater(): BelongsTo {
          return $this->belongsTo(User::class, 'updated_by');
     }

     /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */
     public function scopeActive($query) {
          return $query->where(
               'is_active',
               true
          );
     }

     public function scopeOrdered($query) {
          return $query->orderBy(
               'sort_order'
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */
     public function getIsEnabledAttribute(): bool {
          return $this->is_active;
     }

     public function getStatusBadgeClassAttribute(): string {
          return $this->is_active ? 'bg-success' : 'bg-danger';
     }

     public function isActive(): bool
     {
     return $this->is_active;
     }
}