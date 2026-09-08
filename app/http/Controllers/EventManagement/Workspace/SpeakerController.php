<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileUploadHelper;

use App\Models\Event;
use App\Models\EventSpeaker;
use App\Models\EventOrganization;
use App\Models\EventParticipant;
use App\Models\Organization;

class SpeakerController extends WorkspaceController {
     public function index(Event $event): View {
          return view(
               'event_management.workspace.speakers.index',
               $this->workspace($event)
          );
     }

     public function datatable(Event $event, Request $request) {
          if (! auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($request->ajax()) {
               $addButtonHtml = '';
               if (auth()->user()->canPermission('events.create')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-lg',
                         'label'      => 'ADD',
                         'title'      => 'Add Speaker',
                         'icon'       => '',
                         'color'      => 'warning',
                         'createUrl'  => route('events.speakers.create', $event),
                         'storeUrl'   => route('events.speakers.store', $event),
                         'table'      => 'speakersTable',
                    ]);
               }

               $query = EventSpeaker::query()
                    ->where('event_id', $event->id)
                    ->with([
                         'participant.organizationUser.organization',
                         'participant.organizationUser.user',
                    ])
                    ->withCount('sessions')
                    ->orderBy('sort_order')
                    ->orderBy('name');

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    /*
                    |--------------------------------------------------------------------------
                    | Speaker
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('speaker', function ($row) {
                         $color = $row->color ?: 'primary';
                         $typeBadge = $row->isRepresentative()
                              ? '<span class="badge fs-xs bg-primary ms-1 fs-xs">Representative</span>'
                              : '<span class="badge fs-xs bg-secondary ms-1 fs-xs">External</span>';

                         return '
                              <div class="d-flex align-items-center">
                                   <div class="me-0">
                                        <span class="badge fs-xs bg-'.$color.' p-2 rounded-start rounded-end-0">
                                             <i class="ph-microphone-stage ph-sm"></i>
                                        </span>
                                   </div>

                                   <div class="me-2">
                                        <img src="'.e($row->photo_url).'" class="img-fluid rounded-end" style="width:36px;height:36px;object-fit:cover;">
                                   </div>

                                   <div>
                                        <div class="fw-semibold fs-md">'.e($row->display_name).' '.$typeBadge.'</div>
                                        <div class="text-muted fs-xs">'.e($row->display_designation ?: '-').'</div>
                                   </div>
                              </div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Organization
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('organization', function ($row) {
                         return '
                              <div class="fw-semibold fs-sm">'.e($row->display_organization ?: '-').'</div>
                              <div class="text-muted fs-xs">'.e($row->display_email ?: '-').'</div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Sessions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('sessions', function ($row) {
                         return '
                              <span class="badge fs-xs bg-light border text-dark">'.$row->sessions_count.'</span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('status', function ($row) {
                         return '
                              <span class="badge fs-xs '.$row->status_badge_class.'">
                                   '.$row->status_label.'
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Sort
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('sort', function ($row) {
                         return '
                              <span class="badge fs-xs bg-light border text-dark">
                                   '.$row->sort_order.'
                              </span>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Created
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('created', function ($row) {
                         return '
                              <div class="fw-semibold fs-sm">'.$row->created_at?->format('d M Y').'</div>
                              <div class="text-muted fs-xs">'.$row->created_at?->format('h:i A').'</div>
                         ';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) use ($event) {
                         $buttons = '';
                         if (auth()->user()->canPermission('events.edit')) {
                              $buttons .= action_button('edit', [
                                   'modal-size' => 'modal-lg',
                                   'btn-type'   => 'btn-icon',
                                   'title'      => 'Edit Speaker',
                                   'color'      => 'primary',

                                   'editUrl'    => route('events.speakers.edit', [$event, $row]),
                                   'updateUrl'  => route('events.speakers.update', [$event, $row]),

                                   'table'      => 'speakersTable',
                              ]);
                         }

                         if (auth()->user()->canPermission('events.delete')) {
                              $buttons .= action_button('delete', [
                                   'btn-type' => 'btn-icon',
                                   'title'    => 'Delete Speaker',
                                   'color'    => 'danger',

                                   'url'      => route('events.speakers.destroy', [$event, $row]),
                                   'method'   => 'DELETE',

                                   'table'    => 'speakersTable',
                              ]);
                         }

                         return '<div class="d-flex justify-content-center gap-1">'.$buttons.'</div>
                         ';
                    })
                    ->rawColumns(['speaker','organization','sessions','status','sort','created','actions',])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->make(true);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(Event $event) {
          if (! auth()->user()->canPermission('events.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied',
               ], 403);
          }

          $organizations = $event->organizations()
               ->confirmed()
               ->with('organization')
               ->orderBy('organization_id')
               ->get();

          return view(
               'event_management.workspace.speakers.partials.form',
               [
                    'event'         => $event,
                    'speaker'       => null,
                    'organizations' => $organizations,
                    'participants'  => collect(),
                    'isEdit'        => false,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request, Event $event) {
          $validator = Validator::make($request->all(), [

               'speaker_type' => 'required|in:representative,external',

               'event_participant_id' => [
                    Rule::requiredIf($request->speaker_type === EventSpeaker::TYPE_REPRESENTATIVE),
                    'nullable',
                    'exists:event_participants,id',
               ],

               'name' => [
                    Rule::requiredIf($request->speaker_type === EventSpeaker::TYPE_EXTERNAL),
                    'nullable',
                    'string',
                    'max:255',
               ],

               'designation'  => 'nullable|string|max:255',
               'organization' => 'nullable|string|max:255',
               'email'        => 'nullable|email|max:255',
               'phone'        => 'nullable|string|max:30',

               'bio'       => 'nullable|string',
               'photo'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

               'linkedin'  => 'nullable|url|max:255',
               'twitter'   => 'nullable|url|max:255',
               'website'   => 'nullable|url|max:255',

               'color'      => 'nullable|string|max:30',
               'sort_order' => 'nullable|integer|min:0',
               'is_active'  => 'nullable|boolean',
          ]);

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               $data = $validator->validated();

               $data['event_id']    = $event->id;
               $data['created_by']  = auth()->id();
               $data['color']       = $request->color ?: 'primary';
               $data['sort_order']  = $request->sort_order ?? 0;
               $data['is_active']   = $request->boolean('is_active');

               /*
               |--------------------------------------------------------------------------
               | Representative
               |--------------------------------------------------------------------------
               */
               if ($data['speaker_type'] === EventSpeaker::TYPE_REPRESENTATIVE) {
                    $participant = EventParticipant::findOrFail(
                         $request->event_participant_id
                    );

                    $data['event_participant_id'] = $participant->id;
                    $data['photo'] = null;

                    unset(
                         $data['name'],
                         $data['designation'],
                         $data['organization'],
                         $data['email'],
                         $data['phone']
                    );
               }

               /*
               |--------------------------------------------------------------------------
               | External
               |--------------------------------------------------------------------------
               */
               else {
                    $data['event_participant_id'] = null;

                    if ($request->hasFile('photo')) {
                         $data['photo'] = FileUploadHelper::upload(
                              $request->file('photo'),
                              'speakers'
                         );
                    }
               }

               EventSpeaker::create($data);

               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Speaker created successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to create speaker.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Event $event, EventSpeaker $speaker) {
          if (! auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied',
               ], 403);
          }

          $speaker->load([
               'participant.eventOrganization.organization',
               'participant.organizationUser.organization',
               'participant.organizationUser.user',
          ]);

          $organizations = $event->organizations()
               ->confirmed()
               ->with('organization')
               ->orderBy('id')
               ->get();

          $participants = collect();

          if ($speaker->isRepresentative() && $speaker->participant && $speaker->participant->eventOrganization) {
               $participants = $speaker->participant
                    ->eventOrganization
                    ->participants()
                    ->confirmed()
                    ->with([
                         'organizationUser.organization',
                         'organizationUser.user',
                    ])
                    ->get();
               }
          
          return view(
               'event_management.workspace.speakers.partials.form',
               compact(
                    'event',
                    'speaker',
                    'organizations',
                    'participants'
               ) + [
                    'isEdit' => true,
               ]
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request, Event $event, EventSpeaker $speaker) {
          $validator = Validator::make($request->all(), [
               'speaker_type' => 'required|in:representative,external',

               'event_participant_id' => [
                    Rule::requiredIf($request->speaker_type === EventSpeaker::TYPE_REPRESENTATIVE),
                    'nullable',
                    'exists:event_participants,id',
               ],

               'name' => [
                    Rule::requiredIf($request->speaker_type === EventSpeaker::TYPE_EXTERNAL),
                    'nullable',
                    'string',
                    'max:255',
               ],

               'designation'  => 'nullable|string|max:255',
               'organization' => 'nullable|string|max:255',
               'email'        => 'nullable|email|max:255',
               'phone'        => 'nullable|string|max:30',

               'bio'       => 'nullable|string',
               'photo'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

               'linkedin'  => 'nullable|url|max:255',
               'twitter'   => 'nullable|url|max:255',
               'website'   => 'nullable|url|max:255',

               'color'      => 'nullable|string|max:30',
               'sort_order' => 'nullable|integer|min:0',
               'is_active'  => 'nullable|boolean',
          ]);

          if ($validator->fails()) {
               return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors(),
               ], 422);
          }

          DB::beginTransaction();

          try {
               $data = $validator->validated();

               $data['updated_by'] = auth()->id();
               $data['color']      = $request->color ?: 'primary';
               $data['sort_order'] = $request->sort_order ?? 0;
               $data['is_active']  = $request->boolean('is_active');

               /*
               |--------------------------------------------------------------------------
               | Representative
               |--------------------------------------------------------------------------
               */
               if ($data['speaker_type'] === EventSpeaker::TYPE_REPRESENTATIVE) {
                    $participant = EventParticipant::findOrFail(
                         $request->event_participant_id
                    );

                    $data['event_participant_id'] = $participant->id;

                    if ($speaker->photo) {
                         FileUploadHelper::delete($speaker->photo);
                    }

                    $data['photo'] = null;
                    unset(
                         $data['name'],
                         $data['designation'],
                         $data['organization'],
                         $data['email'],
                         $data['phone']
                    );
               }

               /*
               |--------------------------------------------------------------------------
               | External
               |--------------------------------------------------------------------------
               */
               else {
                    $data['event_participant_id'] = null;

                    if ($request->hasFile('photo')) {
                         if ($speaker->photo) {
                              FileUploadHelper::delete($speaker->photo);
                         }

                         $data['photo'] = FileUploadHelper::upload(
                              $request->file('photo'),
                              'speakers'
                         );
                    }
               }

               $speaker->update($data);
               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Speaker updated successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to update speaker.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Delete
     |--------------------------------------------------------------------------
     */
     public function destroy(Event $event,EventSpeaker $speaker) {
          abort_if(
               $speaker->event_id !== $event->id,
               404
          );

          if (! $speaker->canBeDeleted()) {
               return response()->json([
                    'success' => false,
                    'message' => 'This speaker cannot be deleted because it is assigned to one or more sessions.',
               ], 422);
          }

          DB::beginTransaction();

          try {
               FileUploadHelper::delete($speaker->photo);
               $speaker->delete();
               DB::commit();
               return response()->json([
                    'success' => true,
                    'message' => 'Speaker deleted successfully.',
               ]);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete speaker.',
               ], 500);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Organization Participants
     |--------------------------------------------------------------------------
     */
     public function organizationParticipants(Request $request,Event $event) {
          if (! auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied',
               ], 403);
          }

          $request->validate([
               'event_organization_id' => [
                    'required',
                    'integer',
                    'exists:event_organizations,id',
               ],
          ]);

          $eventOrganization = $event->organizations()
               ->confirmed()
               ->with([
                    'participants.organizationUser.organization',
                    'participants.organizationUser.user',
               ])
               ->findOrFail($request->event_organization_id);

          $participants = $eventOrganization->participants()
               ->confirmed()
               ->with([
                    'organizationUser.organization',
                    'organizationUser.user',
               ])
               ->get();

          return response()->json([
               'success' => true,
               'data' => $participants->map(function ($participant) {
                    return [
                         'id' => $participant->id,
                         'name' => $participant->display_name,
                         'designation' => $participant->organizationUser?->designation,
                         'organization' => $participant->organizationUser?->organization?->name,
                         'email' => $participant->organizationUser?->email,
                         'phone' => $participant->organizationUser?->phone,
                         'avatar' => $participant->avatar_url,
                    ];
               })->values(),
          ]);
     }
}