<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model {
     /*
     |--------------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------------
     */
     protected $table = 'settings';

     /*
     |--------------------------------------------------------------------------
     | Fillable
     |--------------------------------------------------------------------------
     */
     protected $fillable = [
          'setting_group',
          'setting_key',
          'setting_value',
          'setting_type',
          'description',
          'is_public',
          'is_locked',
          'sort_order',
     ];

     /*
     |--------------------------------------------------------------------------
     | Casts
     |--------------------------------------------------------------------------
     */
     protected $casts = [
          'is_public'  => 'boolean',
          'is_locked'  => 'boolean',
          'sort_order' => 'integer',
     ];

     /*
     |--------------------------------------------------------------------------
     | Constants
     |--------------------------------------------------------------------------
     */
     public const TYPE_TEXT = 'text';
     public const TYPE_TEXTAREA = 'textarea';
     public const TYPE_NUMBER = 'number';
     public const TYPE_EMAIL = 'email';
     public const TYPE_URL = 'url';
     public const TYPE_BOOLEAN = 'boolean';
     public const TYPE_SELECT = 'select';
     public const TYPE_JSON = 'json';
     public const TYPE_FILE = 'file';

     /*
     |--------------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------------
     */
     public function scopePublic($query) {
          return $query->where(
               'is_public',
               true
          );
     }

     public function scopeLocked($query) {
          return $query->where(
               'is_locked',
               true
          );
     }

     public function scopeUnlocked($query) {
          return $query->where(
               'is_locked',
               false
          );
     }

     public function scopeGroup($query, string $group) {
          return $query->where(
               'setting_group',
               $group
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

     /**
      * Get setting value by key.
      */
     public static function getValue(string $key,mixed $default = null): mixed {
          return static::query()
               ->where('setting_key', $key)
               ->value('setting_value') ?? $default;
     }

     /**
      * Set setting value.
      */
     public static function setValue(string $key,mixed $value): bool {
          return static::query()
               ->where('setting_key', $key)
               ->update([
                    'setting_value' => $value,
               ]);
     }

     /**
      * Get grouped settings.
      */
     public static function getGroup(string $group) {
          return static::query()
               ->group($group)
               ->orderBy('sort_order')
               ->get();
     }

     /**
      * Available field types.
      */
     public static function types(): array {
          return [
               self::TYPE_TEXT     => 'Text',
               self::TYPE_TEXTAREA => 'Textarea',
               self::TYPE_NUMBER   => 'Number',
               self::TYPE_EMAIL    => 'Email',
               self::TYPE_URL      => 'URL',
               self::TYPE_BOOLEAN  => 'Boolean',
               self::TYPE_SELECT   => 'Select',
               self::TYPE_JSON     => 'JSON',
               self::TYPE_FILE     => 'File',
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */
     public function getDisplayValueAttribute(): mixed {
          return match ($this->type) {
               self::TYPE_BOOLEAN => $this->value
                    ? 'Yes'
                    : 'No',

               default => $this->value,
          };
     }
}