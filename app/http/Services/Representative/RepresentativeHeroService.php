<?php

namespace App\Services\Representative;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\ParticipantAvailability;
use App\Services\Networking\ParticipantRecommendationService;
use App\Models\Event;
use App\Models\EventParticipant;

class RepresentativeHeroService
{
    public function __construct(
        protected ParticipantRecommendationService $recommendationService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Hero
    |--------------------------------------------------------------------------
    */
    public function hero(
        Event $event,
        EventParticipant $participant
    ): array {

        return [

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */
            'availability' => $this->availability(
                $participant
            ),

            /*
            |--------------------------------------------------------------------------
            | Meetings
            |--------------------------------------------------------------------------
            */
            'meetings' => $this->meetings(
                $participant
            ),

            /*
            |--------------------------------------------------------------------------
            | Pending Requests
            |--------------------------------------------------------------------------
            */
            'pending_requests' => $this->pendingRequests(
                $participant
            ),

            /*
            |--------------------------------------------------------------------------
            | Recommendations
            |--------------------------------------------------------------------------
            */
            'recommendations' => $this->recommendations(
                $event,
                $participant
            ),

            /*
            |--------------------------------------------------------------------------
            | Today's Meetings
            |--------------------------------------------------------------------------
            */
            'today_meetings' => $this->todayMeetings(
                $participant
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Availability %
    |--------------------------------------------------------------------------
    */
    protected function availability(
        EventParticipant $participant
    ): int {

        return (int) (
            $participant->availability_percentage ?? 0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Total Meetings
    |--------------------------------------------------------------------------
    */
    protected function meetings(
        EventParticipant $participant
    ): int {

        return Meeting::query()

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

            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Pending Requests
    |--------------------------------------------------------------------------
    */
    protected function pendingRequests(
        EventParticipant $participant
    ): int {

        return MeetingRequest::query()

            ->pending()

            ->where(
                'receiver_participant_id',
                $participant->id
            )

            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Recommendations
    |--------------------------------------------------------------------------
    */
    protected function recommendations(
        Event $event,
        EventParticipant $participant
    ): int {

        return $this->recommendationService

            ->forParticipant(
                event: $event,
                participant: $participant
            )

            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Today's Meetings
    |--------------------------------------------------------------------------
    */
    protected function todayMeetings(
        EventParticipant $participant
    ): int {

        return Meeting::query()

            ->today()

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

            ->count();
    }
}