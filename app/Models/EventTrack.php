<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTrack extends Model {
    use SoftDeletes;

    protected $fillable = [

        'ulid',

        'event_id',

        'name',
        'slug',
        'description',

        'icon',
        'color',

        'sort_order',

        'is_active',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_id'   => 'integer',

        'sort_order' => 'integer',

        'is_active'  => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function event(): BelongsTo {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany {
        return $this->hasMany(EventSession::class);
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */
    public const COLORS = [
        'primary',
        'success',
        'warning',
        'danger',
        'info',
        'secondary',
        'dark',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeOrdered($query) {
        return $query->orderBy('sort_order');
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public static function colors(): array {
        return self::COLORS;
    }

    public function hasSessions(): bool {
        return $this->sessions()->exists();
    }

    public function isActive(): bool {
        return $this->is_active;
    }

    public function canBeDeleted(): bool {
        return ! $this->sessions()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */
    public function getSessionsCountAttribute(): int {
        return $this->sessions()->count();
    }

    public function getStatusBadgeClassAttribute(): string {
        return $this->is_active
            ? 'bg-success'
            : 'bg-secondary';
    }

    public function getStatusLabelAttribute(): string {
        return $this->is_active
            ? 'Active'
            : 'Inactive';
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void {
        static::creating(function ($track) {

            if (empty($track->ulid)) {
                $track->ulid = (string) Str::ulid();
            }

            if (empty($track->slug)) {
                $track->slug = Str::slug($track->name);
            }

        });
    }
}