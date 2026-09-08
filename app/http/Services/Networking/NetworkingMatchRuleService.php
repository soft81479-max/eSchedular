<?php

namespace App\Services\Networking;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NetworkingMatchRuleService
{
     /*
     |--------------------------------------------------------------------------
     | Apply Networking Match Rules
     |--------------------------------------------------------------------------
     |
     | CandidateQueryService produces a normalized candidate pair:
     |
     |     ep1.id < ep2.id
     |
     | That ordering exists only to prevent duplicate candidate pairs.
     | It does not determine sender / receiver direction.
     |
     | This service:
     |
     | - Applies configured participant-type matching rules
     | - Checks both possible directions
     | - Requires at least one allowed direction
     | - Resolves the canonical sender / receiver
     | - Exposes matching-rule metadata
     |--------------------------------------------------------------------------
     */
     public function apply(
          Builder $query
     ): Builder {

          return $query

               /*
               |--------------------------------------------------------------------------
               | Event
               |--------------------------------------------------------------------------
               */
               ->join(
                    'events as networking_event',
                    'networking_event.id',
                    '=',
                    'eo1.event_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Candidate Participant Types
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_participant_types as networking_ept1',
                    'networking_ept1.id',
                    '=',
                    'eo1.event_participant_type_id'
               )

               ->join(
                    'event_participant_types as networking_ept2',
                    'networking_ept2.id',
                    '=',
                    'eo2.event_participant_type_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Forward Match Rule
               |--------------------------------------------------------------------------
               |
               | Candidate A -> Candidate B
               |--------------------------------------------------------------------------
               */
               ->leftJoin(
                    'event_type_match_rules as networking_forward_rule',
                    function ($join) {

                         $join
                              ->on(
                                   'networking_forward_rule.event_type_id',
                                   '=',
                                   'networking_event.event_type_id'
                              )

                              ->on(
                                   'networking_forward_rule.source_participant_type_id',
                                   '=',
                                   'networking_ept1.participant_type_id'
                              )

                              ->on(
                                   'networking_forward_rule.target_participant_type_id',
                                   '=',
                                   'networking_ept2.participant_type_id'
                              );
                    }
               )

               /*
               |--------------------------------------------------------------------------
               | Reverse Match Rule
               |--------------------------------------------------------------------------
               |
               | Candidate B -> Candidate A
               |--------------------------------------------------------------------------
               */
               ->leftJoin(
                    'event_type_match_rules as networking_reverse_rule',
                    function ($join) {

                         $join
                              ->on(
                                   'networking_reverse_rule.event_type_id',
                                   '=',
                                   'networking_event.event_type_id'
                              )

                              ->on(
                                   'networking_reverse_rule.source_participant_type_id',
                                   '=',
                                   'networking_ept2.participant_type_id'
                              )

                              ->on(
                                   'networking_reverse_rule.target_participant_type_id',
                                   '=',
                                   'networking_ept1.participant_type_id'
                              );
                    }
               )

               /*
               |--------------------------------------------------------------------------
               | At Least One Allowed Direction
               |--------------------------------------------------------------------------
               */
               ->where(
                    function (Builder $query) {

                         $query
                              ->where(
                                   'networking_forward_rule.is_match_allowed',
                                   true
                              )

                              ->orWhere(
                                   'networking_reverse_rule.is_match_allowed',
                                   true
                              );
                    }
               )

               /*
               |--------------------------------------------------------------------------
               | Match Rule Payload
               |--------------------------------------------------------------------------
               */
               ->addSelect([

                    /*
                    |--------------------------------------------------------------------------
                    | Match Direction
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN 'forward'

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN 'reverse'

                              ELSE NULL

                         END as match_direction
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Match Rule ID
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN networking_forward_rule.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN networking_reverse_rule.id

                              ELSE NULL

                         END as match_rule_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Match Priority Score
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN networking_forward_rule.priority_score

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN networking_reverse_rule.priority_score

                              ELSE 0

                         END as match_priority_score
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Auto Recommend
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN networking_forward_rule.auto_recommend

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN networking_reverse_rule.auto_recommend

                              ELSE 0

                         END as match_auto_recommend
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Visibility
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN networking_forward_rule.visibility_enabled

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN networking_reverse_rule.visibility_enabled

                              ELSE 0

                         END as match_visibility_enabled
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Participant
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ep1.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ep2.id

                         END as sender_participant_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Participant ULID
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ep1.ulid

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ep2.ulid

                         END as sender_participant_ulid
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Participant
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ep2.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ep1.id

                         END as receiver_participant_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Participant ULID
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ep2.ulid

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ep1.ulid

                         END as receiver_participant_ulid
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Organization User
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ep1.organization_user_id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ep2.organization_user_id

                         END as sender_organization_user_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Organization User
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ep2.organization_user_id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ep1.organization_user_id

                         END as receiver_organization_user_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Event Organization
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN eo1.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN eo2.id

                         END as sender_event_organization_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Event Organization
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN eo2.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN eo1.id

                         END as receiver_event_organization_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Event Participant Type
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN eo1.event_participant_type_id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN eo2.event_participant_type_id

                         END as sender_event_participant_type_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Event Participant Type
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN eo2.event_participant_type_id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN eo1.event_participant_type_id

                         END as receiver_event_participant_type_id
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Name
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN user1.name

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN user2.name

                         END as sender_name
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Name
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN user2.name

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN user1.name

                         END as receiver_name
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Photo
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN user1.avatar

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN user2.avatar

                         END as sender_photo
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Photo
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN user2.avatar

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN user1.avatar

                         END as receiver_photo
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Designation
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ou1.designation

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ou2.designation

                         END as sender_designation
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Designation
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN ou2.designation

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN ou1.designation

                         END as receiver_designation
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Organization
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN org1.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN org2.id

                         END as sender_organization_id
                    "),

                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN org1.name

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN org2.name

                         END as sender_organization
                    "),

                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN org1.logo

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN org2.logo

                         END as sender_organization_logo
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Organization
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN org2.id

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN org1.id

                         END as receiver_organization_id
                    "),

                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN org2.name

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN org1.name

                         END as receiver_organization
                    "),

                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN org2.logo

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN org1.logo

                         END as receiver_organization_logo
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Sender Availability
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN pa1.status

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN pa2.status

                         END as sender_availability
                    "),

                    /*
                    |--------------------------------------------------------------------------
                    | Receiver Availability
                    |--------------------------------------------------------------------------
                    */
                    DB::raw("
                         CASE

                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN pa2.status

                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN pa1.status

                         END as receiver_availability
                    "),
               ]);
     }
}