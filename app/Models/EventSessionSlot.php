<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSessionSlot extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'ulid',

        'event_session_id',
        'event_time_slot_id',

        'sort_order',

        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'event_session_id' => 'integer',
        'event_time_slot_id' => 'integer',

        'sort_order' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function session(): BelongsTo
    {
        return $this->belongsTo(
            EventSession::class,
            'event_session_id'
        );
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(
            EventTimeSlot::class,
            'event_time_slot_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($model) {

            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

        });
    }
}