<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchingStrategy extends Model {
    protected $table = 'matching_strategies';

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
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function eventTypes(): HasMany
    {
        return $this->hasMany(EventType::class);
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
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function activate(): void {
        $this->update([
            'is_active' => true,
        ]);
    }

    public function deactivate(): void {
        $this->update([
            'is_active' => false,
        ]);
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