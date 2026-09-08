<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class EventBooth extends Model {
    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'ulid',

        'event_id',
        'event_organization_id',

        'code',
        'name',
        'type',
        'location',

        'status',

        'sort_order',

        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'event_organization_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function getRouteKeyName(): string {
        return 'ulid';
    }

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function eventOrganization() {
        return $this->belongsTo(
            EventOrganization::class,
            'event_organization_id'
        );
    }

    public function creator() {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater() {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public static function statuses(): array {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_ASSIGNED,
            self::STATUS_RESERVED,
            self::STATUS_INACTIVE,
        ];
    }
    
    protected static function booted(): void {
        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }
}