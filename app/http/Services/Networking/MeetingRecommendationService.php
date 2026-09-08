<?php

namespace App\Services\Networking;

use Illuminate\Support\Collection;

class MeetingRecommendationService {
     /*
     |--------------------------------------------------------------------------
     | Enrich Opportunities
     |--------------------------------------------------------------------------
     */
     public function enrich(Collection $opportunities): Collection {
          return $opportunities
               ->map(function ($row) {
                    /*
                    |--------------------------------------------------------------------------
                    | Compatibility
                    |--------------------------------------------------------------------------
                    |
                    | Compatibility calculation will eventually be composed from:
                    |
                    | - participant matching
                    | - networking rules
                    | - availability
                    | - event-specific matching signals
                    |
                    | For now we expose the rule priority as the available
                    | networking score rather than inventing another formula.
                    |
                    |--------------------------------------------------------------------------
                    */
                    $row->compatibility_score = (int) ($row->match_priority_score ?? 0);

                    /*
                    |--------------------------------------------------------------------------
                    | Ranking
                    |--------------------------------------------------------------------------
                    */
                    $row->ranking_score = (int) ($row->match_priority_score ?? 0);

                    /*
                    |--------------------------------------------------------------------------
                    | Recommendation
                    |--------------------------------------------------------------------------
                    */
                    $row->auto_recommendation = (bool) ($row->match_auto_recommend ?? false);
                    return $row;
               });
     }
}