<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\NetworkingMode;
use App\Models\MatchingStrategy;
use App\Models\EventMatchRule;
use App\Models\EventParticipantType;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class OverviewController extends WorkspaceController {
     public function index(Event $event): View {
          return view(
               'event_management.workspace.overview.index',
               $this->workspace($event)
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Event Configuration
     |--------------------------------------------------------------------------
     */
     public function editConfiguration(Event $event): View {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $eventTypes = EventType::query()
               ->active()
               ->orderBy('sort_order')
               ->get();

          $networkingModes = NetworkingMode::query()
               ->active()
               ->orderBy('sort_order')
               ->get();

          $matchingStrategies = MatchingStrategy::query()
               ->active()
               ->orderBy('sort_order')
               ->get();

          return view(
               'event_management.workspace.overview.partials.configuration',
               array_merge(
                    $this->workspace($event),
                    compact(
                         'eventTypes',
                         'networkingModes',
                         'matchingStrategies'
                    )
               )
          );
     }

     public function updateConfiguration(Request $request, Event $event): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $validated = $request->validate([
               'default_slot_duration' => [
                    'required',
                    'integer',
                    'min:15',
               ],
               'default_slot_capacity' => [
                    'required',
                    'integer',
                    'min:1',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Audit
          |--------------------------------------------------------------------------
          */

          $validated['updated_by'] = auth()->id();

          /*
          |--------------------------------------------------------------------------
          | Update Configuration
          |--------------------------------------------------------------------------
          */

          $event->update($validated);

          return response()->json([
               'status' => 'success',
               'message' => 'Event configuration updated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Participant Ecosystem
     |--------------------------------------------------------------------------
     */
     public function editParticipantEcosystem(Event $event): View {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $participantTypes = $event->participantTypes()
               //->active()
               ->orderBy('sort_order')
               ->get();

          return view(
               'event_management.workspace.overview.partials.participant-ecosystem',
               array_merge(
                    $this->workspace($event),
                    compact('participantTypes')
               )
          );
     }

     public function updateParticipantEcosystem(Request $request, Event $event): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $validated = $request->validate([
               'participant_types' => [
                    'required',
                    'array',
               ],
               'participant_types.*.id' => [
                    'required',
                    'integer',
               ],
               'participant_types.*.can_initiate_meetings' => [
                    'required',
                    'boolean',
               ],
               'participant_types.*.can_receive_meetings' => [
                    'required',
                    'boolean',
               ],
               'participant_types.*.is_active' => [
                    'required',
                    'boolean',
               ],
               'participant_types.*.priority_level' => [
                    'required',
                    'integer',
                    'min:1',
               ],
               'participant_types.*.max_daily_meetings' => [
                    'required',
                    'integer',
                    'min:0',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Update Participant Ecosystem
          |--------------------------------------------------------------------------
          */
          foreach ($validated['participant_types'] as $participantType) {
               $eventParticipantType = EventParticipantType::query()
                    ->where('event_id', $event->id)
                    ->findOrFail($participantType['id']);

               $eventParticipantType->update([
                    'can_initiate_meetings' => $participantType['can_initiate_meetings'],
                    'can_receive_meetings'  => $participantType['can_receive_meetings'],
                    'priority_level'        => $participantType['priority_level'],
                    'max_daily_meetings'    => $participantType['max_daily_meetings'],
                    'is_active'             => $participantType['is_active'],
                    'updated_by'            => auth()->id(),
               ]);
          }

          return response()->json([
               'status'  => 'success',
               'message' => 'Participant ecosystem updated successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Matching Rules
     |--------------------------------------------------------------------------
     */
     public function editMatchingRules(Event $event): View {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $participantTypes = EventParticipantType::query()
               ->where('event_id', $event->id)
               ->active()
               ->orderBy('sort_order')
               ->get();

          $matchingRules = EventMatchRule::query()
               ->where('event_id', $event->id)
               ->get()
               ->keyBy(function ($rule) {
                    return $rule->source_event_participant_type_id.'-'.$rule->target_event_participant_type_id;
               });

          return view(
               'event_management.workspace.overview.partials.matching-rules',
               array_merge(
                    $this->workspace($event),
                    compact(
                         'participantTypes',
                         'matchingRules'
                    )
               )
          );
     }

     public function updateMatchingRules(Request $request, Event $event): JsonResponse {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $validated = $request->validate([
               'rules' => [
                    'required',
                    'array',
               ],
               'rules.*' => [
                    'array',
               ],
               'rules.*.*.enabled' => [
                    'required',
                    'boolean',
               ],
               'rules.*.*.priority' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:999',
               ],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Update Matching Rules
          |--------------------------------------------------------------------------
          */
          foreach ($validated['rules'] as $sourceParticipantTypeId => $targets) {
               foreach ($targets as $targetParticipantTypeId => $rule) {
                    $matchingRule = EventMatchRule::query()
                         ->where('event_id', $event->id)
                         ->where('source_event_participant_type_id', $sourceParticipantTypeId)
                         ->where('target_event_participant_type_id', $targetParticipantTypeId)
                         ->first();

                    if (!$matchingRule) {
                         continue;
                    }

                    $matchingRule->update([
                         'is_match_allowed' => $rule['enabled'],
                         'priority_score'   => $rule['priority'],
                         'updated_by'       => auth()->id(),
                    ]);
               }
          }

          return response()->json([
               'status'  => 'success',
               'message' => 'Matching rules updated successfully.',
          ]);
     }
}