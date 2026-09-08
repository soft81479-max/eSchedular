<?php

namespace App\Services\Networking;

use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class ParticipantRecommendationService {
     public function __construct(
          protected CandidateQueryService $candidateQueryService,
          protected NetworkingEligibilityService $eligibilityService,
          protected NetworkingMatchRuleService $matchRuleService,
          protected MeetingConflictService $meetingConflictService,
          protected RecommendationRankingService $rankingService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Event Recommendations
     |--------------------------------------------------------------------------
     |
     | Returns all valid recommendation opportunities for the event.
     |
     | Used by:
     |
     | - Super Admin
     | - Admin
     | - Organizer
     |--------------------------------------------------------------------------
     */
     public function forEvent(Event $event,array $filters = []): Collection {
          return $this->recommendations(
               event: $event,
               participant: null,
               filters: $filters
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Recommendations
     |--------------------------------------------------------------------------
     |
     | Returns only recommendation opportunities involving the supplied
     | event participant.
     |
     | Used by:
     |
     | - Representative
     | - Participant
     |--------------------------------------------------------------------------
     */
     public function forParticipant(Event $event,EventParticipant $participant,array $filters = []): Collection {
          /*
          |--------------------------------------------------------------------------
          | Participant Event Validation
          |--------------------------------------------------------------------------
          */
          abort_unless(
               $participant->eventOrganization?->event_id === $event->id,
               404
          );

          // return $this->recommendations(
          //      event: $event,
          //      participant: $participant,
          //      filters: $filters
          // );

          return $this->normalizeForParticipant(
               recommendations: $this->recommendations(
                    event: $event,
                    participant: $participant,
                    filters: $filters
               ),
               participant: $participant
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Recommendation Pipeline
     |--------------------------------------------------------------------------
     */
     public function recommendations(Event $event,?EventParticipant $participant = null,array $filters = []): Collection {
          /*
          |--------------------------------------------------------------------------
          | Candidate Pool
          |--------------------------------------------------------------------------
          */
          $query = $this->candidateQueryService->query(
               event: $event,
               participant: $participant
          );

          /*
          |--------------------------------------------------------------------------
          | Networking Eligibility
          |--------------------------------------------------------------------------
          */
          $query = $this->eligibilityService->apply(
               query: $query
          );

          /*
          |--------------------------------------------------------------------------
          | Networking Match Rules
          |--------------------------------------------------------------------------
          |
          | Applies the event-configured participant ecosystem and matching
          | rules, then resolves the canonical sender / receiver direction.
          |--------------------------------------------------------------------------
          */
          $query = $this->matchRuleService->apply(
               query: $query
          );

          /*
          |--------------------------------------------------------------------------
          | Meeting Conflicts
          |--------------------------------------------------------------------------
          */
          $query = $this->meetingConflictService->apply(
               query: $query
          );

          /*
          |--------------------------------------------------------------------------
          | Filters
          |--------------------------------------------------------------------------
          */
          $query = $this->applyFilters(
               query: $query,
               filters: $filters
          );

          /*
          |--------------------------------------------------------------------------
          | Candidate Collection
          |--------------------------------------------------------------------------
          */
          $candidates = $query
               ->orderBy(
                    'event_time_slots.start_at'
               )
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Recommendation Ranking
          |--------------------------------------------------------------------------
          */
          return $this->rankingService->rank(
               candidates: $candidates
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Filters
     |--------------------------------------------------------------------------
     */
     protected function applyFilters(Builder $query,array $filters = []): Builder {
          /*
          |--------------------------------------------------------------------------
          | Availability
          |--------------------------------------------------------------------------
          */
          if (!empty($filters['availability'])) {
               $query->where(function (Builder $scope) use ($filters) {
                    $scope
                         ->where(
                              'pa1.status',
                              $filters['availability']
                         )
                         ->orWhere(
                              'pa2.status',
                              $filters['availability']
                         );
               });
          }

          /*
          |--------------------------------------------------------------------------
          | Event Time Slot
          |--------------------------------------------------------------------------
          */
          if (!empty($filters['event_time_slot_id'])) {
               $query->where(
                    'event_time_slots.id',
                    $filters['event_time_slot_id']
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Organization
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['organization_id'])) {
               $query->where(function (Builder $scope) use ($filters) {
                    $scope
                         ->where(
                              'eo1.organization_id',
                              $filters['organization_id']
                         )
                         ->orWhere(
                              'eo2.organization_id',
                              $filters['organization_id']
                         );
               });
          }

          /*
          |--------------------------------------------------------------------------
          | Participant Type
          |--------------------------------------------------------------------------
          */
          if (! empty($filters['participant_type'])) {
               $query->where(function (Builder $scope) use ($filters) {
                    $scope
                         ->where(
                              'eo1.event_participant_type_id',
                              $filters['participant_type']
                         )

                         ->orWhere(
                              'eo2.event_participant_type_id',
                              $filters['participant_type']
                         );
               });
          }

          /*
          |--------------------------------------------------------------------------
          | Compatibility
          |--------------------------------------------------------------------------
          */
          if (!empty($filters['compatibility'])) {
               $query->whereRaw(
                    '
                         CASE
                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN networking_forward_rule.priority_score
                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN networking_reverse_rule.priority_score
                              ELSE 0
                         END >= ?
                    ',
                    [
                         (int) $filters['compatibility'],
                    ]
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Auto Recommend
          |--------------------------------------------------------------------------
          */
          if (array_key_exists('auto_recommend', $filters)) {
               $query->whereRaw(
                    '
                         CASE
                              WHEN networking_forward_rule.is_match_allowed = 1
                                   THEN networking_forward_rule.auto_recommend
                              WHEN networking_reverse_rule.is_match_allowed = 1
                                   THEN networking_reverse_rule.auto_recommend
                              ELSE 0
                         END = ?
                    ',
                    [
                         (bool) $filters['auto_recommend'] ? 1 : 0,
                    ]
               );
          }
          return $query;
     }

     /*
     |--------------------------------------------------------------------------
     | Normalize For Participant
     |--------------------------------------------------------------------------
     |
     | Converts sender/receiver into a single "other participant"
     | model for Representative Workspace.
     |
     */
     private function normalizeForParticipant(Collection $recommendations,EventParticipant $participant): Collection {
          return $recommendations
               ->map(function ($recommendation) use ($participant) {
                    if ($recommendation->sender_participant_id == $participant->id) {
                         $recommendation->participant_id = $recommendation->receiver_participant_id;
                         $recommendation->participant_name = $recommendation->receiver_name;
                         $recommendation->participant_photo = $recommendation->receiver_photo;
                         $recommendation->participant_designation = $recommendation->receiver_designation;
                         $recommendation->participant_organization = $recommendation->receiver_organization;
                         $recommendation->participant_availability = $recommendation->receiver_availability;
                    } else {
                         $recommendation->participant_id = $recommendation->sender_participant_id;
                         $recommendation->participant_name = $recommendation->sender_name;
                         $recommendation->participant_photo = $recommendation->sender_photo;
                         $recommendation->participant_designation = $recommendation->sender_designation;
                         $recommendation->participant_organization = $recommendation->sender_organization;
                         $recommendation->participant_availability = $recommendation->sender_availability;
                    }
                    return $recommendation;
               })
               ->values();
     }
}