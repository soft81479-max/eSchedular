<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\ParticipantAvailability;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CandidateQueryService
{
     /*
     |--------------------------------------------------------------------------
     | Candidate Query
     |--------------------------------------------------------------------------
     |
     | Builds the raw networking candidate universe for an event.
     |
     | Responsibilities:
     |
     | - Shared eligible availability
     | - Same event
     | - Meeting slots only
     | - Bookable slots only
     | - Unique participant pairs
     | - Optional participant scope
     |
     | This service does NOT decide:
     |
     | - Participant / organization networking eligibility
     | - Participant-type matching rules
     | - Sender / receiver direction
     | - Meeting conflicts
     | - Compatibility
     | - Recommendation ranking
     |--------------------------------------------------------------------------
     */
     public function query(
          Event $event,
          ?EventParticipant $participant = null
     ): Builder {

          return DB::table(
               'participant_availabilities as pa1'
          )

               /*
               |--------------------------------------------------------------------------
               | Shared Availability
               |--------------------------------------------------------------------------
               */
               ->join(
                    'participant_availabilities as pa2',
                    'pa1.event_time_slot_id',
                    '=',
                    'pa2.event_time_slot_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Meeting Eligible Availability
               |--------------------------------------------------------------------------
               */
               ->whereIn(
                    'pa1.status',
                    ParticipantAvailability::meetingEligibleStatuses()
               )

               ->whereIn(
                    'pa2.status',
                    ParticipantAvailability::meetingEligibleStatuses()
               )

               /*
               |--------------------------------------------------------------------------
               | Unique Candidate Pair
               |--------------------------------------------------------------------------
               |
               | The lower participant ID becomes candidate A only to prevent:
               |
               |     A + B
               |     B + A
               |
               | from appearing as duplicate raw pairs.
               |
               | This does NOT determine sender / receiver direction.
               |--------------------------------------------------------------------------
               */
               ->whereColumn(
                    'pa1.event_participant_id',
                    '<',
                    'pa2.event_participant_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Optional Participant Scope
               |--------------------------------------------------------------------------
               */
               ->when(
                    $participant,
                    function (Builder $query) use ($participant) {

                         $query->where(
                              function (Builder $scope) use ($participant) {

                                   $scope
                                        ->where(
                                             'pa1.event_participant_id',
                                             $participant->id
                                        )

                                        ->orWhere(
                                             'pa2.event_participant_id',
                                             $participant->id
                                        );
                              }
                         );
                    }
               )

               /*
               |--------------------------------------------------------------------------
               | Candidate Participants
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_participants as ep1',
                    'ep1.id',
                    '=',
                    'pa1.event_participant_id'
               )

               ->join(
                    'event_participants as ep2',
                    'ep2.id',
                    '=',
                    'pa2.event_participant_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Event Organizations
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_organizations as eo1',
                    'eo1.id',
                    '=',
                    'ep1.event_organization_id'
               )

               ->join(
                    'event_organizations as eo2',
                    'eo2.id',
                    '=',
                    'ep2.event_organization_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Same Event
               |--------------------------------------------------------------------------
               */
               ->where(
                    'eo1.event_id',
                    $event->id
               )

               ->where(
                    'eo2.event_id',
                    $event->id
               )

               /*
               |--------------------------------------------------------------------------
               | Organizations
               |--------------------------------------------------------------------------
               */
               ->join(
                    'organizations as org1',
                    'org1.id',
                    '=',
                    'eo1.organization_id'
               )

               ->join(
                    'organizations as org2',
                    'org2.id',
                    '=',
                    'eo2.organization_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Organization Users
               |--------------------------------------------------------------------------
               */
               ->join(
                    'organization_users as ou1',
                    'ou1.id',
                    '=',
                    'ep1.organization_user_id'
               )

               ->join(
                    'organization_users as ou2',
                    'ou2.id',
                    '=',
                    'ep2.organization_user_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Users
               |--------------------------------------------------------------------------
               */
               ->join(
                    'users as user1',
                    'user1.id',
                    '=',
                    'ou1.user_id'
               )

               ->join(
                    'users as user2',
                    'user2.id',
                    '=',
                    'ou2.user_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Event Time Slot
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_time_slots',
                    'event_time_slots.id',
                    '=',
                    'pa1.event_time_slot_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Meeting Slot
               |--------------------------------------------------------------------------
               */
               ->where(
                    'event_time_slots.type',
                    'meeting'
               )

               /*
               |--------------------------------------------------------------------------
               | Bookable Slot
               |--------------------------------------------------------------------------
               */
               ->where(
                    'event_time_slots.is_bookable',
                    true
               )

               /*
               |--------------------------------------------------------------------------
               | Candidate Payload
               |--------------------------------------------------------------------------
               */
               ->select(
                    /*
                    |--------------------------------------------------------------------------
                    | Candidate A
                    |--------------------------------------------------------------------------
                    */
                    'ep1.id as candidate_a_participant_id',
                    'ep1.ulid as candidate_a_participant_ulid',

                    'ep1.organization_user_id as candidate_a_organization_user_id',
                    'eo1.id as candidate_a_event_organization_id',
                    'eo1.event_participant_type_id as candidate_a_event_participant_type_id',
                    'user1.name as candidate_a_name',
                    'user1.avatar as candidate_a_photo',
                    'ou1.designation as candidate_a_designation',
                    'org1.id as candidate_a_organization_id',
                    'org1.name as candidate_a_organization',
                    'org1.logo as candidate_a_organization_logo',

                    /*
                    |--------------------------------------------------------------------------
                    | Candidate B
                    |--------------------------------------------------------------------------
                    */
                    'ep2.id as candidate_b_participant_id',
                    'ep2.ulid as candidate_b_participant_ulid',

                    'ep2.organization_user_id as candidate_b_organization_user_id',
                    'eo2.id as candidate_b_event_organization_id',
                    'eo2.event_participant_type_id as candidate_b_event_participant_type_id',
                    'user2.name as candidate_b_name',
                    'user2.avatar as candidate_b_photo',
                    'ou2.designation as candidate_b_designation',
                    'org2.id as candidate_b_organization_id',
                    'org2.name as candidate_b_organization',
                    'org2.logo as candidate_b_organization_logo',

                    /*
                    |--------------------------------------------------------------------------
                    | Slot
                    |--------------------------------------------------------------------------
                    */
                    'event_time_slots.id as event_time_slot_id',
                    'event_time_slots.type',
                    'event_time_slots.mode',
                    'event_time_slots.capacity',
                    'event_time_slots.start_at',
                    'event_time_slots.end_at',

                    /*
                    |--------------------------------------------------------------------------
                    | Availability
                    |--------------------------------------------------------------------------
                    */
                    'pa1.status as candidate_a_availability',
                    'pa2.status as candidate_b_availability'
               );
     }
}