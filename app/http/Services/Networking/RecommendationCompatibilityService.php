<?php

namespace App\Services\Networking;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RecommendationCompatibilityService {
     /*
     |--------------------------------------------------------------------------
     | Apply Compatibility Rules
     |--------------------------------------------------------------------------
     */
     public function apply(Builder $query): Builder {
          return $query
               /*
               |--------------------------------------------------------------------------
               | Event
               |--------------------------------------------------------------------------
               */
               ->join(
                    'events',
                    'events.id',
                    '=',
                    'eo1.event_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Sender Event Participant Type
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_participant_types as ept1',
                    'ept1.id',
                    '=',
                    'eo1.event_participant_type_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Receiver Event Participant Type
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_participant_types as ept2',
                    'ept2.id',
                    '=',
                    'eo2.event_participant_type_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Event Type Match Rules
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_type_match_rules as etmr',
                    function ($join) {
                         $join
                              ->on(
                                   'etmr.event_type_id',
                                   '=',
                                   'events.event_type_id'
                              )

                              ->on(
                                   'etmr.source_participant_type_id',
                                   '=',
                                   'ept1.participant_type_id'
                              )

                              ->on(
                                   'etmr.target_participant_type_id',
                                   '=',
                                   'ept2.participant_type_id'
                              );
                    }
               )

               /*
               |--------------------------------------------------------------------------
               | Match Allowed
               |--------------------------------------------------------------------------
               */
               ->where(
                    'etmr.is_match_allowed',
                    true
               )

               /*
               |--------------------------------------------------------------------------
               | Compatibility Score
               |--------------------------------------------------------------------------
               */
               ->addSelect([
                    DB::raw('etmr.priority_score as compatibility_score'),
                    DB::raw('etmr.auto_recommend as auto_recommend'),
                    DB::raw('etmr.visibility_enabled as visibility_enabled'),
               ]);
     }
}