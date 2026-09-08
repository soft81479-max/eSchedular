<?php

namespace App\Services\MyEvents;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\EventParticipant;
use App\Models\MeetingRequest;
use App\Services\Networking\ParticipantRecommendationService;

class MatchmakingService {
     public function __construct(
          protected ParticipantRecommendationService $recommendationService,
     ) {
     }

     /*
     |--------------------------------------------------------------------------
     | Workspace
     |--------------------------------------------------------------------------
     */
     public function workspace(array $workspace,): array{
          $event = $workspace['event'];
          $participant = $workspace['participant'];

          return [
               'matchmakingStatistics' => $this->summary(
                    $event,
                    $participant
               ),

               'filters' => $this->filters(
                    $event
               ),

               'recommendedMatches' => $this->recommendations(
                    $event,
                    $participant
               ),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Summary
     |--------------------------------------------------------------------------
     */
     protected function summary(Event $event,EventParticipant $participant,): array {
          $recommendations = $this->recommendationService
               ->forParticipant(
                    event: $event,
                    participant: $participant
               );

          return [
               /*
               |--------------------------------------------------------------------------
               | Total Recommendations
               |--------------------------------------------------------------------------
               */
               'recommendations' => $recommendations->count(),

               /*
               |--------------------------------------------------------------------------
               | High Compatibility
               |--------------------------------------------------------------------------
               */
               'high_compatibility' => $recommendations
                    ->where('compatibility_score', '>=', 80)
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Pending Requests
               |--------------------------------------------------------------------------
               */
               'pending_requests' => MeetingRequest::query()
                    ->pending()
                    ->where(function ($query) use ($participant) {
                         $query
                              ->where(
                                   'sender_participant_id',
                                   $participant->id
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $participant->id
                              );
                    })
                    ->count(),

               /*
               |--------------------------------------------------------------------------
               | Confirmed Meetings
               |--------------------------------------------------------------------------
               */
               'confirmed_meetings' => Meeting::query()
                    ->accepted()
                    ->where(function ($query) use ($participant) {
                         $query
                              ->where(
                                   'sender_participant_id',
                                   $participant->id
                              )

                              ->orWhere(
                                   'receiver_participant_id',
                                   $participant->id
                              );
                    })
                    ->count(),
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Filters
     |--------------------------------------------------------------------------
     */
     protected function filters(Event $event,): array {
          return [
               /*
               |--------------------------------------------------------------------------
               | Organizations
               |--------------------------------------------------------------------------
               */
               'organizations' => $event
                    ->organizations()
                    ->with('organization')
                    ->get(),

               /*
               |--------------------------------------------------------------------------
               | Participant Types
               |--------------------------------------------------------------------------
               */
               'participantTypes' => $event
                    ->participantTypes()
                    ->orderBy('sort_order')
                    ->get(),

               /*
               |--------------------------------------------------------------------------
               | Availability
               |--------------------------------------------------------------------------
               */
               'availability' => [
                    'preferred',
                    'available',
               ],

               /*
               |--------------------------------------------------------------------------
               | Compatibility
               |--------------------------------------------------------------------------
               */
               'compatibility' => [
                    90,
                    80,
                    70,
               ],
          ];
     }

     /*
     |--------------------------------------------------------------------------
     | Recommendations
     |--------------------------------------------------------------------------
     */
     protected function recommendations(Event $event,EventParticipant $participant,array $filters = [],) {
          return $this->recommendationService
               ->forParticipant(
                    event: $event,
                    participant: $participant,
                    filters: $filters
               )

               ->map(function ($recommendation) use ($participant) {
                    /*
                    |--------------------------------------------------------------------------
                    | Determine Other Participant
                    |--------------------------------------------------------------------------
                    */
                    $isSender = $recommendation->sender_participant_id == $participant->id;

                    $recommendation->participant_ulid = $isSender
                         ? $recommendation->receiver_participant_ulid
                         : $recommendation->sender_participant_ulid;

                    $recommendation->participant_name = $isSender
                         ? $recommendation->receiver_name
                         : $recommendation->sender_name;

                    $recommendation->participant_photo = $isSender
                         ? $recommendation->receiver_photo
                         : $recommendation->sender_photo;

                    $recommendation->participant_designation = $isSender
                         ? $recommendation->receiver_designation
                         : $recommendation->sender_designation;

                    $recommendation->participant_organization = $isSender
                         ? $recommendation->receiver_organization
                         : $recommendation->sender_organization;

                    $recommendation->participant_organization_logo = $isSender
                         ? $recommendation->receiver_organization_logo
                         : $recommendation->sender_organization_logo;

                    $recommendation->participant_availability = $isSender
                         ? $recommendation->receiver_availability
                         : $recommendation->sender_availability;

                    return $recommendation;
               })
               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function recommendationsForDatatable(array $workspace,array $filters = [],){
          return $this->recommendations(
               $workspace['event'],
               $workspace['participant'],
               filters: $filters,
          );
     }
}