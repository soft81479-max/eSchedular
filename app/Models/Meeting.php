<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model {
     use SoftDeletes;

     /*
     |--------------------------------------------------------------------------
     | Status
     |--------------------------------------------------------------------------
     */

     const STATUS_PENDING   = 'pending';
     const STATUS_ACCEPTED  = 'accepted';
     const STATUS_REJECTED  = 'rejected';
     const STATUS_CANCELLED = 'cancelled';
     const STATUS_COMPLETED = 'completed';
     const STATUS_NO_SHOW   = 'no_show';

     /*
     |--------------------------------------------------------------------------
     | Meeting Mode
     |--------------------------------------------------------------------------
     */

     const MODE_PHYSICAL = 'physical';
     const MODE_VIRTUAL  = 'virtual';
     const MODE_HYBRID   = 'hybrid';

     /*
     |--------------------------------------------------------------------------
     | Fillable
     |--------------------------------------------------------------------------
     */

     protected $fillable = [
          'ulid',

          'event_time_slot_id',
          'meeting_request_id',

          'sender_participant_id',
          'receiver_participant_id',

          'is_recommended',
          'recommendation_score',

          'meeting_mode',

          'meeting_link',
          'location',

          'status',

          'accepted_at',
          'started_at',
          'completed_at',
          'cancelled_at',

          'qr_code',

          'notes',

          'created_by',
          'updated_by',
     ];

     /*
     |--------------------------------------------------------------------------
     | Casts
     |--------------------------------------------------------------------------
     */
     protected $casts = [

          'event_time_slot_id'     => 'integer',
          'meeting_request_id'     => 'integer',

          'sender_participant_id'  => 'integer',
          'receiver_participant_id'=> 'integer',

          'recommendation_score'   => 'integer',

          'is_recommended'         => 'boolean',

          'accepted_at'            => 'datetime',
          'started_at'             => 'datetime',
          'completed_at'           => 'datetime',
          'cancelled_at'           => 'datetime',
     ];

     public function getRouteKeyName(): string {
          return 'ulid';
     }

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function meetingRequest(): BelongsTo {
          return $this->belongsTo(
               MeetingRequest::class
          );
     }

     public function timeSlot(): BelongsTo {
          return $this->belongsTo(
               EventTimeSlot::class,
               'event_time_slot_id'
          );
     }

     public function senderParticipant(): BelongsTo {
          return $this->belongsTo(
               EventParticipant::class,
               'sender_participant_id'
          );
     }

     public function receiverParticipant(): BelongsTo {
          return $this->belongsTo(
               EventParticipant::class,
               'receiver_participant_id'
          );
     }

     public function activitiesX(): HasMany {
          return $this->hasMany(
               MeetingActivity::class
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Audit
     |--------------------------------------------------------------------------
     */
     public function creator(): BelongsTo {
          return $this->belongsTo(
               User::class,
               'created_by'
          );
     }

     public function updater(): BelongsTo {
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
     public function scopePending($query) {
          return $query->where('status', self::STATUS_PENDING);
     }

     public function scopeAccepted($query) {
          return $query->where('status', self::STATUS_ACCEPTED);
     }

     public function scopeCompleted($query) {
          return $query->where('status', self::STATUS_COMPLETED);
     }

     public function scopeActive($query) {
          return $query->whereIn('status', [
               self::STATUS_PENDING,
               self::STATUS_ACCEPTED,
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */
     public static function statuses(): array {
          return [
               self::STATUS_PENDING,
               self::STATUS_ACCEPTED,
               self::STATUS_REJECTED,
               self::STATUS_CANCELLED,
               self::STATUS_COMPLETED,
               self::STATUS_NO_SHOW,
          ];
     }

     public static function modes(): array {
          return [
               self::MODE_PHYSICAL,
               self::MODE_VIRTUAL,
               self::MODE_HYBRID,
          ];
     }

     public function isPending(): bool {
          return $this->status === self::STATUS_PENDING;
     }

     public function isAccepted(): bool {
          return $this->status === self::STATUS_ACCEPTED;
     }

     public function isCompleted(): bool {
          return $this->status === self::STATUS_COMPLETED;
     }

     /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */
     public function getParticipantCountAttribute(): int {
          return 2;
     }

     /*
     |--------------------------------------------------------------------------
     | Boot
     |--------------------------------------------------------------------------
     */
     protected static function booted(): void {
          static::creating(function ($meeting) {
               if (empty($meeting->ulid)) {
                    $meeting->ulid = (string) Str::ulid();
               }
          });
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */
     public function otherParticipant(EventParticipant $participant): EventParticipant {
          return $this->sender_participant_id == $participant->id
               ? $this->receiverParticipant
               : $this->senderParticipant;
     }

     public function getStatusBadgeAttribute(): string {
          return match ($this->status) {
               self::STATUS_ACCEPTED => '<span class="badge bg-success">Confirmed</span>',
               self::STATUS_PENDING => '<span class="badge bg-warning">Pending</span>',
               self::STATUS_COMPLETED => '<span class="badge bg-primary">Completed</span>',
               self::STATUS_CANCELLED => '<span class="badge bg-danger">Cancelled</span>',
               self::STATUS_NO_SHOW => '<span class="badge bg-dark">No Show</span>',
               default => '<span class="badge bg-secondary">'.ucfirst($this->status).'</span>',
          };
     }
}