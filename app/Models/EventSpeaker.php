<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventSpeaker extends Model {
     use SoftDeletes;

     /*
     |--------------------------------------------------------------------------
     | Status
     |--------------------------------------------------------------------------
     */

     const STATUS_ACTIVE   = 1;
     const STATUS_INACTIVE = 0;

     /*
     |--------------------------------------------------------------------------
     | Speaker Types
     |--------------------------------------------------------------------------
     */

     const TYPE_REPRESENTATIVE = 'representative';
     const TYPE_EXTERNAL       = 'external';

     /*
     |--------------------------------------------------------------------------
     | Fillable
     |--------------------------------------------------------------------------
     */
     protected $fillable = [

          'ulid',

          'event_id',

          'speaker_type',
          'event_participant_id',

          'name',
          'designation',
          'organization',

          'email',
          'phone',

          'bio',
          'photo',

          'linkedin',
          'twitter',
          'website',

          'color',

          'sort_order',

          'is_active',

          'created_by',
          'updated_by',
     ];

     /*
     |--------------------------------------------------------------------------
     | Casts
     |--------------------------------------------------------------------------
     */
     protected $casts = [

          'event_id' => 'integer',

          'event_participant_id' => 'integer',

          'sort_order' => 'integer',

          'is_active' => 'boolean',
     ];

     /*
     |--------------------------------------------------------------------------
     | Relationships
     |--------------------------------------------------------------------------
     */
     public function event(): BelongsTo     {
          return $this->belongsTo(Event::class);
     }

     public function sessions(): BelongsToMany {
          return $this->belongsToMany(
               EventSession::class,
               'event_session_speakers'
          )
          ->withPivot([
               'display_order',
               'is_primary',
          ])
          ->withTimestamps()
          ->orderByPivot('display_order');
     }

     /*
     |--------------------------------------------------------------------------
     | Event Participant
     |--------------------------------------------------------------------------
     */
     public function participant(): BelongsTo {
          return $this->belongsTo(
               EventParticipant::class,
               'event_participant_id'
          );
     }

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
     public function scopeActive($query) {
          return $query->where('is_active', true);
     }

     public function scopeOrdered($query) {
          return $query
               ->orderBy('sort_order')
               ->orderBy('name');
     }

     /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */
     public function canBeDeleted(): bool {
          return ! $this->sessions()->exists();
     }

     public function isActive(): bool {
          return $this->is_active;
     }

     public function getStatusLabelAttribute(): string {
          return $this->is_active
               ? 'Active'
               : 'Inactive';
     }

     public function getStatusBadgeClassAttribute(): string {
          return $this->is_active
               ? 'bg-success'
               : 'bg-secondary';
     }

     public static function types(): array {
          return [
               self::TYPE_REPRESENTATIVE,
               self::TYPE_EXTERNAL,
          ];
     }

     public function isRepresentative(): bool {
          return $this->speaker_type === self::TYPE_REPRESENTATIVE;
     }

     public function isExternal(): bool {
          return $this->speaker_type === self::TYPE_EXTERNAL;
     }

     /*
     |--------------------------------------------------------------------------
     | Accessors
     |--------------------------------------------------------------------------
     */

     public function getPhotoUrlAttribute(): string {
          if ($this->isRepresentative()) {
               return $this->participant?->avatar_url
                    ?? asset('images/defaults/avatar.png');
          }

          return ($this->photo && Storage::disk('public')->exists($this->photo))
               ? Storage::url($this->photo)
               : asset('images/defaults/avatar.png');
     }

     public function getDisplayNameAttribute(): string {
          if ($this->isRepresentative()) {
               return $this->participant?->display_name ?? '';
          }

          return $this->name ?? '';
     }

     public function getDisplayDesignationAttribute(): ?string {
          if ($this->isRepresentative()) {
               return $this->participant?->organizationUser?->designation;
          }

          return $this->designation;
     }

     public function getDisplayOrganizationAttribute(): ?string {
          if ($this->isRepresentative()) {
               return $this->participant?->organizationUser?->organization?->name;
          }

          return $this->organization;
     }

     public function getDisplayEmailAttribute(): ?string {
          if ($this->isRepresentative()) {
               return $this->participant?->organizationUser?->email;
          }

          return $this->email;
     }

     public function getDisplayPhoneAttribute(): ?string {
          if ($this->isRepresentative()) {
               return $this->participant?->organizationUser?->phone;
          }

          return $this->phone;
     }

     /*
     |--------------------------------------------------------------------------
     | Boot
     |--------------------------------------------------------------------------
     */
     protected static function booted(): void {
          static::creating(function ($speaker) {
               if (empty($speaker->ulid)) {
                    $speaker->ulid = (string) Str::ulid();
               }
          });
     }
}