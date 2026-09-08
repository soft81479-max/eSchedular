<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOrganization;
use App\Models\ParticipantTypeMatchRule;

class OrganizationCompatibilityService {
    /*
    |--------------------------------------------------------------------------
    | Can Organizations Match
    |--------------------------------------------------------------------------
    */
    public function canOrganizationsMatch(EventOrganization $source,EventOrganization $target): bool {
        /*
        |--------------------------------------------------------------------------
        | Missing Participant Types
        |--------------------------------------------------------------------------
        */
        if (!$source->participant_type_id || !$target->participant_type_id) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Matchmaking Disabled
        |--------------------------------------------------------------------------
        */
        if (!$source->matchmaking_enabled || $source->status !== EventOrganization::STATUS_ACTIVE) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Same Organization
        |--------------------------------------------------------------------------
        */
        if ($source->id === $target->id) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Match Rule
        |--------------------------------------------------------------------------
        */
        return ParticipantTypeMatchRule::query()
            ->where(
                'event_id',
                $source->event_id
            )
            ->where(
                'source_participant_type_id',
                $source->participant_type_id
            )
            ->where(
                'target_participant_type_id',
                $target->participant_type_id
            )
            ->where(
                'is_match_allowed',
                true
            )
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibility Score
    |--------------------------------------------------------------------------
    */
    public function compatibilityScore(EventOrganization $source,EventOrganization $target): int {
        /*
        |--------------------------------------------------------------------------
        | Missing Participant Types
        |--------------------------------------------------------------------------
        */
        if (!$source->participant_type_id || !$target->participant_type_id) {
            return 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Match Rule
        |--------------------------------------------------------------------------
        */
        $rule = ParticipantTypeMatchRule::query()
            ->where(
                'event_id',
                $source->event_id
            )
            ->where(
                'source_participant_type_id',
                $source->participant_type_id
            )
            ->where(
                'target_participant_type_id',
                $target->participant_type_id
            )
            ->where(
                'is_match_allowed',
                true
            )
            ->first();

        if (!$rule) {
            return 0;
        }

        return (int) $rule->priority_score;
    }

    /*
    |--------------------------------------------------------------------------
    | Compatible Organizations
    |--------------------------------------------------------------------------
    */
    public function compatibleOrganizations(EventOrganization $source) {
        /*
        |--------------------------------------------------------------------------
        | Missing Participant Type
        |--------------------------------------------------------------------------
        */
        if (!$source->participant_type_id) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Allowed Target Types
        |--------------------------------------------------------------------------
        */
        $allowedTargetTypeIds = ParticipantTypeMatchRule::query()
            ->where(
                'event_id',
                $source->event_id
            )
            ->where(
                'source_participant_type_id',
                $source->participant_type_id
            )
            ->where(
                'is_match_allowed',
                true
            )
            ->pluck(
                'target_participant_type_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Organizations
        |--------------------------------------------------------------------------
        */
        return EventOrganization::query()
            ->with([
                'organization',
                'participantType',
                'participants.representative.user',
            ])
            ->where(
                'event_id',
                $source->event_id
            )
            ->where(
                'matchmaking_enabled',
                true
            )
            ->where(
                'status',
                EventOrganization::STATUS_ACTIVE
            )
            ->whereIn(
                'participant_type_id',
                $allowedTargetTypeIds
            )
            ->where(
                'id',
                '!=',
                $source->id
            )
            ->get()
            ->map(function ($organization) use ($source) {
                $organization->compatibility_score= $this->compatibilityScore($source,$organization);
                return $organization;
            })
            ->sortByDesc(
                'compatibility_score'
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Event Compatibility Graph
    |--------------------------------------------------------------------------
    */
    public function compatibilityGraph(Event $event) {
        return ParticipantTypeMatchRule::query()
            ->with([
                'sourceType',
                'targetType',
            ])
            ->where(
                'event_id',
                $event->id
            )
            ->where(
                'is_match_allowed',
                true
            )
            ->orderByDesc(
                'priority_score'
            )
            ->get();
    }
}