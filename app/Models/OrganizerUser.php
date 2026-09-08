<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerUser extends Model {
     /*
     |--------------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------------
     */
     protected $table = 'organizer_users';

     /*
     |--------------------------------------------------------------------------
     | Statuses
     |--------------------------------------------------------------------------
     */
     public const STATUS_ACTIVE   = 'active';
     public const STATUS_INACTIVE = 'inactive';

     /*
     |--------------------------------------------------------------------------
     | Fillable
     |--------------------------------------------------------------------------
     */
     protected $fillable = [
          'organizer_id',
          'user_id',

          'is_primary',
          'status',
          'joined_at',

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
          'user_id' => 'integer',

          'created_by' => 'integer',
          'updated_by' => 'integer',

          'is_primary' => 'boolean',

          'joined_at' => 'datetime',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */

     /**
     * Organizer.
     */
     public function organizer() {
          return $this->belongsTo(
               Organizer::class,
               'organizer_id'
          );
     }

     /**
     * User.
     */
     public function user() {
          return $this->belongsTo(
               User::class,
               'user_id'
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

     public function scopePrimary($query) {
          return $query->where(
               'is_primary',
               true
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */
     public function isInactive(): bool
     {
          return $this->status === self::STATUS_INACTIVE;
     }

     public function isPrimary(): bool {
          return $this->is_primary;
     }

     public function isActive(): bool {
          return $this->status === self::STATUS_ACTIVE;
     }

     public static function statuses(): array
     {
          return [
               self::STATUS_ACTIVE,
               self::STATUS_INACTIVE,
          ];
     }
}