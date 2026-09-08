<?php

namespace App\Services\Representative;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\ParticipantAvailability;

class RepresentativeDashboardService
{
    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    public function dashboard(
        Event $event,
        $participant
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Availability
        |--------------------------------------------------------------------------
        */
        $availability = $this->availabilityStats(
            $participant
        );

        /*
        |--------------------------------------------------------------------------
        | Meetings
        |--------------------------------------------------------------------------
        */
        $meetings = $this->meetingStats(
            $participant
        );

        /*
        |--------------------------------------------------------------------------
        | Requests
        |--------------------------------------------------------------------------
        */
        $requests = $this->requestStats(
            $participant
        );

        /*
        |--------------------------------------------------------------------------
        | KPI
        |--------------------------------------------------------------------------
        */
        return compact(
            'availability',
            'meetings',
            'requests'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */
    protected function availabilityStats($participant): array
    {
        $preferred = ParticipantAvailability::query()
            ->preferred()
            ->where(
                'event_participant_id',
                $participant->id
            )
            ->count();

        $available = ParticipantAvailability::query()
            ->available()
            ->where(
                'event_participant_id',
                $participant->id
            )
            ->count();

        $unavailable = ParticipantAvailability::query()
            ->unavailable()
            ->where(
                'event_participant_id',
                $participant->id
            )
            ->count();

        return [

            'preferred'   => $preferred,

            'available'   => $available,

            'unavailable' => $unavailable,

            'percentage'  =>
                $participant->availability_percentage,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Meeting Requests
    |--------------------------------------------------------------------------
    */
    protected function requestStats($participant): array
    {
        $incoming = MeetingRequest::query()
            ->pending()
            ->where(
                'receiver_participant_id',
                $participant->id
            )
            ->count();

        $outgoing = MeetingRequest::query()
            ->pending()
            ->where(
                'sender_participant_id',
                $participant->id
            )
            ->count();

        return [

            'incoming' => $incoming,

            'outgoing' => $outgoing,

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Meetings
    |--------------------------------------------------------------------------
    */
    protected function meetingStats($participant): array
    {
        $query = Meeting::query()

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

            });

        return [

            'scheduled' =>
                (clone $query)->count(),

            'completed' =>
                (clone $query)
                    ->completed()
                    ->count(),

            'today' =>
                (clone $query)
                    ->today()
                    ->count(),

        ];
    }
}