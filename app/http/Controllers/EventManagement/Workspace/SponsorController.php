<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SponsorController extends WorkspaceController
{
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(Event $event): View
     {
          /*
          |--------------------------------------------------------------------------
          | Sponsors
          |--------------------------------------------------------------------------
          */
          $sponsors = DB::table('event_organizations as eo')

               /*
               |--------------------------------------------------------------------------
               | Event Participant Type
               |--------------------------------------------------------------------------
               */
               ->join(
                    'event_participant_types as ept',
                    'ept.id',
                    '=',
                    'eo.event_participant_type_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Base Participant Type
               |--------------------------------------------------------------------------
               */
               ->join(
                    'participant_types as pt',
                    'pt.id',
                    '=',
                    'ept.participant_type_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               ->join(
                    'organizations as org',
                    'org.id',
                    '=',
                    'eo.organization_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Current Event
               |--------------------------------------------------------------------------
               */
               ->where(
                    'eo.event_id',
                    $event->id
               )

               /*
               |--------------------------------------------------------------------------
               | Sponsor
               |--------------------------------------------------------------------------
               */
               ->where(
                    'pt.slug',
                    'sponsor'
               )

               /*
               |--------------------------------------------------------------------------
               | Payload
               |--------------------------------------------------------------------------
               */
               ->select([
                    'eo.id as event_organization_id',
                    'eo.organization_id',
                    'eo.status as participation_status',
                    'eo.matchmaking_enabled',

                    'org.name as organization_name',
                    'org.logo as organization_logo',

                    'ept.name as event_participant_type_name',
               ])

               ->orderBy(
                    'org.name'
               )

               ->get();

          /*
          |--------------------------------------------------------------------------
          | Sponsor Representatives
          |--------------------------------------------------------------------------
          */
          $representatives = DB::table('event_participants as ep')

               /*
               |--------------------------------------------------------------------------
               | Organization User
               |--------------------------------------------------------------------------
               */
               ->join(
                    'organization_users as ou',
                    'ou.id',
                    '=',
                    'ep.organization_user_id'
               )

               /*
               |--------------------------------------------------------------------------
               | User
               |--------------------------------------------------------------------------
               */
               ->join(
                    'users as u',
                    'u.id',
                    '=',
                    'ou.user_id'
               )

               /*
               |--------------------------------------------------------------------------
               | Sponsor Event Organizations Only
               |--------------------------------------------------------------------------
               */
               ->whereIn(
                    'ep.event_organization_id',
                    $sponsors->pluck('event_organization_id')
               )

               /*
               |--------------------------------------------------------------------------
               | Payload
               |--------------------------------------------------------------------------
               */
               ->select([
                    'ep.id as event_participant_id',
                    'ep.event_organization_id',
                    'ep.status as participant_status',
                    'ep.matchmaking_enabled',

                    'ou.designation',

                    'u.name',
                    'u.email',
                    'u.phone',
                    'u.avatar',
               ])

               ->orderBy(
                    'u.name'
               )

               ->get()

               ->groupBy(
                    'event_organization_id'
               );

          /*
          |--------------------------------------------------------------------------
          | Workspace
          |--------------------------------------------------------------------------
          */
          return view(
               'event_management.workspace.sponsors.index',
               array_merge(
                    $this->workspace($event),
                    [
                         'sponsors' => $sponsors,
                         'representatives' => $representatives,
                    ]
               )
          );
     }
}