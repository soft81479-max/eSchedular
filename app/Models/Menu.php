<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class Menu extends Model {
     /*
     |--------------------------------------------------------------------------
     | Table
     |--------------------------------------------------------------------------
     */
     protected $table = 'menus';

     /*
     |--------------------------------------------------------------------------
     | Fillable
     |--------------------------------------------------------------------------
     */
     protected $fillable = [
          'name',
          'description',
          'slug',
          'route',
          'icon',
          'parent_id',
          'is_leaf',
          'is_clickable',
          'active_patterns',
          'sort_order',
          'is_active',
          'route_key',
     ];

     /*
     |--------------------------------------------------------------------------
     | Casts
     |--------------------------------------------------------------------------
     */
     protected $casts = [
          'is_leaf'        => 'boolean',
          'is_clickable'   => 'boolean',
          'is_active'      => 'boolean',
          'sort_order'     => 'integer',
          'parent_id'      => 'integer',
     ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Parent menu.
     */
     public function parent() {
          return $this->belongsTo(
               self::class,
               'parent_id'
          );
     }

     /**
     * Child menus.
     */
     public function children() {
          return $this->hasMany(
               self::class,
               'parent_id'
          )->orderBy('sort_order');
     }

     /**
     * Role mappings.
     */
     public function roleMenus() {
          return $this->hasMany(
               RoleMenu::class
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function roles() {
          return $this->belongsToMany(
               Role::class,
               'role_menus',
               'menu_id',
               'role_id'
          );
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

     public function scopeParents($query) {
          return $query->whereNull(
               'parent_id'
          );
     }

     public function scopeChildren($query) {
          return $query->whereNotNull(
               'parent_id'
          );
     }

     public function scopeClickable($query) {
          return $query->where(
               'is_clickable',
               true
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

     /**
     * Determine whether the menu has children.
     */
     public function getHasChildrenAttribute(): bool {
          return $this->children()
               ->exists();
     }

     /**
     * Active patterns as array.
     */
     public function getPatternsAttribute(): array {
          if (empty($this->active_patterns)) {
               return [];
          }

          return collect(
               explode(',', $this->active_patterns)
          )
          ->map(fn ($pattern) => trim($pattern))
          ->filter()
          ->values()
          ->toArray();
     }

     /**
     * Check if current request matches.
     */
     public function isActive(): bool {
          return collect($this->patterns)
               ->contains(
                    fn ($pattern) => request()->is($pattern)
               );
     }

     public function getUrlAttribute(): string {
          if ($this->route_key && \Illuminate\Support\Facades\Route::has($this->route_key)) {
               return route($this->route_key);
          }

          return 'javascript:void(0)';
     }

     public function getHasRouteAttribute(): bool {
          return !empty($this->route_key) && route()->has($this->route_key);
     }

     public function hasActiveChild(): bool {
          return $this->children
               ->filter(fn ($child) => $child->isActive())
               ->isNotEmpty();
     }

     public function getIsChildAttribute(): bool {
          return !is_null($this->parent_id);
     }

     public function getIsParentAttribute(): bool {
          return is_null($this->parent_id);
     }
}