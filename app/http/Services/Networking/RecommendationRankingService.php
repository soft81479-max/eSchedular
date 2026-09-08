<?php

namespace App\Services\Networking;

use Illuminate\Support\Collection;

class RecommendationRankingService
{
     /*
     |--------------------------------------------------------------------------
     | Weights
     |--------------------------------------------------------------------------
     */
     protected const MATCH_PRIORITY_WEIGHT = 10;
     protected const PREFERRED_WEIGHT      = 100;
     protected const AUTO_RECOMMEND_WEIGHT = 50;

     /*
     |--------------------------------------------------------------------------
     | Rank Candidates
     |--------------------------------------------------------------------------
     */
     public function rank(
          Collection $candidates
     ): Collection {

          return $candidates

               ->map(function ($candidate) {

                    /*
                    |--------------------------------------------------------------------------
                    | Compatibility Score
                    |--------------------------------------------------------------------------
                    |
                    | Expose the configured match-rule priority through the
                    | recommendation contract.
                    |--------------------------------------------------------------------------
                    */
                    $candidate->compatibility_score =
                         (int) ($candidate->match_priority_score ?? 0);

                    /*
                    |--------------------------------------------------------------------------
                    | Recommendation Metadata
                    |--------------------------------------------------------------------------
                    */
                    $candidate->auto_recommend =
                         (bool) ($candidate->match_auto_recommend ?? false);

                    $candidate->visibility_enabled =
                         (bool) ($candidate->match_visibility_enabled ?? false);

                    /*
                    |--------------------------------------------------------------------------
                    | Ranking Score
                    |--------------------------------------------------------------------------
                    */
                    $candidate->ranking_score =
                         $this->calculateScore($candidate);

                    return $candidate;
               })

               ->sortByDesc(
                    'ranking_score'
               )

               ->values();
     }

     /*
     |--------------------------------------------------------------------------
     | Calculate Score
     |--------------------------------------------------------------------------
     */
     protected function calculateScore(
          object $candidate
     ): int {

          return
               $this->matchPriorityScore($candidate)
               +
               $this->availabilityScore($candidate)
               +
               $this->autoRecommendScore($candidate);

          /*
          |--------------------------------------------------------------------------
          | Future Ranking Signals
          |--------------------------------------------------------------------------
          |
          | + $this->industryScore($candidate)
          | + $this->interestScore($candidate)
          | + $this->sponsorScore($candidate)
          | + $this->partnerScore($candidate)
          | + $this->historicalInteractionScore($candidate)
          | + $this->aiScore($candidate)
          |
          */
     }

     /*
     |--------------------------------------------------------------------------
     | Match Priority Score
     |--------------------------------------------------------------------------
     */
     protected function matchPriorityScore(
          object $candidate
     ): int {

          return
               (int) ($candidate->match_priority_score ?? 0)
               *
               self::MATCH_PRIORITY_WEIGHT;
     }

     /*
     |--------------------------------------------------------------------------
     | Availability Score
     |--------------------------------------------------------------------------
     |
     | Preferred availability receives additional ranking weight.
     |--------------------------------------------------------------------------
     */
     protected function availabilityScore(
          object $candidate
     ): int {

          $score = 0;

          if (
               ($candidate->sender_availability ?? null)
               ===
               'preferred'
          ) {
               $score += self::PREFERRED_WEIGHT;
          }

          if (
               ($candidate->receiver_availability ?? null)
               ===
               'preferred'
          ) {
               $score += self::PREFERRED_WEIGHT;
          }

          return $score;
     }

     /*
     |--------------------------------------------------------------------------
     | Auto Recommend Score
     |--------------------------------------------------------------------------
     */
     protected function autoRecommendScore(
          object $candidate
     ): int {

          return
               (bool) ($candidate->match_auto_recommend ?? false)
                    ? self::AUTO_RECOMMEND_WEIGHT
                    : 0;
     }
}