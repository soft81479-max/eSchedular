<?php

namespace App\Http\Controllers\EventManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileUploadHelper;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

use Exception;
use App\Models\Country;
use App\Models\EventType;
use App\Models\ParticipantType;
use App\Models\NetworkingMode;
use App\Models\MatchingStrategy;
use App\Models\EventTypeMatchRule;
use App\Models\EventParticipantType;
use App\Models\EventMatchRule;
use App\Models\Organizer;
use App\Models\EventBooth;
use App\Models\Event;
use App\Models\User;

use Illuminate\Http\RedirectResponse;
use App\Services\Shared\EventAccessService;
use App\Services\EventManagement\ToolbarBuilder;

class EventController extends Controller {
    protected $eventAccessService;
    protected $toolbarBuilder;

    public function __construct(EventAccessService $eventAccessService, ToolbarBuilder $toolbarBuilder) {
        $this->eventAccessService = $eventAccessService;
        $this->toolbarBuilder = $toolbarBuilder;
    }
     /**
     * Display Events Listing.
     */
     public function index() {
          if (!auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('event_management.events.index');
     }

     /**
     * Event Datatable.
     */
     private function permissions(): array {
          return [
               'view'   => auth()->user()->canPermission('events.view'),
               'edit'   => auth()->user()->canPermission('events.edit'),
               'delete' => auth()->user()->canPermission('events.delete'),
               'delete' => auth()->user()->canPermission('events.delete'),
          ];
     }

     public function datatable(Request $request) {
          if ($request->ajax()) {
               $addButtonHtml = '';

               if (! auth()->user()->canPermission('events.view')) {
                    return response()->json([
                         'success' => false,
                         'message' => 'Unauthorized action',
                    ], 403);
               }

               if (auth()->user()->canPermission('events.create')) {
                    $addButtonHtml = action_button('view', [
                         'href'  => route('events.create'),
                         'label' => 'ADD',
                         'title' => 'Add Event',
                         'icon'  => '',
                         'color' => 'warning',
                    ]);
               }

               $events = $this->eventAccessService->query();
               $permissions = $this->permissions();

               return DataTables::of($events)
                    ->setRowClass('table-border-double')
                    ->addColumn('card', function (Event $row) use ($permissions) {
                         $buttons = '';
                         
                         $actions = '';
                         $showDropdown = false;

                         /*
                         |--------------------------------------------------------------------------
                         | View
                         |--------------------------------------------------------------------------
                         */
                         if ($permissions['view']) {
                              $buttons .= action_button('view', [
                                   'href'  => route('events.overview.index', $row->ulid),
                                   'label' => '',
                                   'title' => 'View Event',
                                   'color' => 'secondary',
                                   'btn-type' => 'btn-icon',
                              ]);
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Edit
                         |--------------------------------------------------------------------------
                         */
                         if ($permissions['edit'] && $row->isEditable()) {
                              $buttons .= action_button('view', [
                                   'href'  => route('events.edit', $row->ulid),
                                   'label' => '',
                                   'title' => 'Edit Event',
                                   'btn-type' => 'btn-icon',
                                   'icon' => 'ph-pencil-line',
                                   'color' => 'primary',
                              ]);
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Publish
                         |--------------------------------------------------------------------------
                         */
                         if ($permissions['edit'] && $row->isPublishable()) {
                              $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('publishEvent', [
                                        'label'  => 'Publish',
                                        'title'  => '',
                                        'url'    => route('events.publish', $row->ulid),
                                        'method' => 'POST',
                                        'table'  => 'eventsTable',
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-paper-plane-tilt',
                                   ]).
                              '</div>';
                         }
                         
                         /*
                         |--------------------------------------------------------------------------
                         | Archive
                         |--------------------------------------------------------------------------
                         */
                         if ($permissions['edit'] && $row->isArchivable()) {
                              $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('archive', [
                                        'label'  => 'Archive',
                                        'title'  => '',
                                        'url'    => route('events.archive', $row->ulid),
                                        'method' => 'POST',
                                        'table'  => 'eventsTable',
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-dark d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-archive-tray',
                                   ]).
                              '</div>';
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Delete
                         |--------------------------------------------------------------------------
                         */
                         if ($permissions['delete'] && $row->isDeletable()) {
                              if (!empty($actions)) {
                                   $actions .= '<div class="dropdown-divider"></div>';
                              }
                              $actions .= '<div class="dropdown-item p-0 d-flex justify-content-start align-items-center">'.
                                   action_button('delete', [
                                        'label'  => 'Delete',
                                        'title'  => '',
                                        'url'    => route('events.destroy', $row->ulid),
                                        'method' => 'DELETE',
                                        'table'  => 'eventsTable',
                                        'class'  => 'd-flex justify-content-start align-items-center w-100 text-start border-0 bg-transparent py-1 px-3 text-danger d-block m-0',
                                        'color'  => '',
                                        'icon'  => 'ph-trash',
                                   ]).
                              '</div>';
                         }

                         if ($actions) {
                              $showDropdown = '<div class="dropdown">
                                   <button type="button" class="btn btn-sm  px-2 btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ph-dots-three-vertical ph-sm me-1"></i>
                                   </button>
                                   <div class="dropdown-menu dropdown-menu-end py-1">'.$actions.'</div>
                              </div>';
                         }

                         return '
                              <div class="card mb-0 shadow-none border-0 rounded-0 bg-transparent">
                                   <div class="card-body p-0">
                                        <div class="d-flex flex-column flex-md-row align-items-start">
                                             <!-- Logo -->
                                             <img src="'.$row->logo_url.'" class="img-fluid rounded me-md-2 mb-2 mb-md-0 flex-shrink-0" style="width:72px;height:72px;object-fit:cover;">
                                             <div class="flex-fill">
                                                  <!-- Header -->
                                                  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start mb-2">
                                                       <div>
                                                            <h6 class="mb-0 fw-semibold">'.$row->name.'</h6>
                                                            <div class="small text-muted">'.$row->eventType?->name.'</div>
                                                            <div class="fw-semibold">'.$row->organizer?->name.'</div>

                                                            <div class="d-flex align-items-center small text-muted text-wrap">
                                                                 <i class="ph-map-pin fs-xs me-1"></i>'.$row->venue.'
                                                            </div>
                                                       </div>

                                                       <div class="mt-2 mt-lg-0 text-start text-lg-end">
                                                            '.$row->status_badge.'
                                                            '.$row->networking_badge.'
                                                            '.$row->matching_strategy_badge.'
                                                            '.$row->compatibility_badge.'
                                                            <div class="d-flex justify-content-lg-end align-items-center auto-ms small pt-1">
                                                                 <i class="ph-calendar fs-sm me-1"></i>'.$row->date_range.'
                                                            </div>
                                                            <span class="small fw-semibold pt-1">['.$row->duration.']</span>
                                                       </div>
                                                  </div>

                                                  <!-- Statistics -->
                                                  <div class="row border-top pt-2">
                                                       <div class="col-4 col-md-3 col-lg-2">
                                                            <div class="small text-muted">Participant Types</div>
                                                            <div class="d-flex align-items-center fw-semibold">
                                                                 <i class="ph-buildings ph-sm me-1 text-muted"></i>'.$row->participant_types_count.'
                                                            </div>
                                                       </div>

                                                       <div class="col-4 col-md-3 col-lg-2">
                                                            <div class="small text-muted">Organizations</div>
                                                            <div class="d-flex align-items-center fw-semibold">
                                                                 <i class="ph-users-three ph-sm me-1 text-muted"></i>'.$row->organizations_count.'
                                                            </div>
                                                       </div>

                                                       <div class="col-4 col-md-3 col-lg-2">
                                                            <div class="small text-muted">Participants</div>
                                                            <div class="d-flex align-items-center fw-semibold">
                                                                 <i class="ph-user-circle ph-sm me-1 text-muted"></i>'.$row->participants_count.'
                                                            </div>
                                                       </div>

                                                       <div class="col-12 col-md-3 col-lg-6 pt-1 pt-md-0 d-flex align-items-end">
                                                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center w-100 gap-2">
                                                                 <!-- Left Side: Badges (Always side-by-side) -->
                                                                 <div class="d-flex flex-wrap gap-1">
                                                                      <span class="badge d-flex align-items-center fs-xs bg-light border text-body">
                                                                           <i class="ph-clock fs-sm me-1"></i>'.$row->default_slot_duration.' Minutes
                                                                      </span>
                                                                      <span class="badge d-flex align-items-center fs-xs bg-light border text-body">
                                                                           <i class="ph-users ph-sm me-1"></i>Capacity '.$row->default_slot_capacity.'
                                                                      </span>
                                                                 </div>

                                                                 <div class="d-flex align-items-center flex-wrap gap-1">
                                                                      <!-- Left Item: Buttons -->
                                                                      <div class="d-flex flex-wrap gap-1">'.$buttons.'</div>
                                                                      '. $showDropdown .'
                                                                 </div>
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              </div>';
                    })
                    ->rawColumns(['card',])
                    ->with('custom_button', ['button_html' => $addButtonHtml])
                    ->make(true);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create() {
          if (!auth()->user()->canPermission('events.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $showOrganizerSelector = auth()->user()->canPermission('events.create');
          /*
          |--------------------------------------------------------------------------
          | Organizers
          |--------------------------------------------------------------------------
          */
          $organizers = [];
          if ($showOrganizerSelector) {
               $organizers = Organizer::query()
                    ->select('id', 'name')
                    ->whereHas('users', function ($query) {
                         $query->where('users.status', 'active')
                              ->where('is_verified', 1)
                              ->whereNull('users.deleted_at');
                    })
                    ->orderBy('name')
                    ->get();
          }

          /*
          |--------------------------------------------------------------------------
          | Country List
          |--------------------------------------------------------------------------
          */
          $countries = Country::query()
               ->select('name')
               ->where('is_active', 1)
               ->orderBy('name')
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Event Types
          |--------------------------------------------------------------------------
          */
          $eventTypes = EventType::query()
               ->select('id', 'name', 'icon', 'color')
               ->where('is_active', 1)
               ->orderBy('sort_order')
               ->orderBy('name')
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Networking Modes
          |--------------------------------------------------------------------------
          */
          $networkingModes = NetworkingMode::query()
               ->select('id', 'name', 'icon', 'color')
               ->where('is_active', 1)
               ->orderBy('sort_order')
               ->orderBy('name')
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Matching Strategies
          |--------------------------------------------------------------------------
          */
          $matchingStrategies = MatchingStrategy::query()
               ->select('id', 'name', 'icon', 'color')
               ->where('is_active', 1)
               ->orderBy('sort_order')
               ->orderBy('name')
               ->get();

          /*
          |--------------------------------------------------------------------------
          | Timezones
          |--------------------------------------------------------------------------
          */
          $timezones = timezone_identifiers_list();

          return view(
               'event_management.events.create',
               compact(
                    'showOrganizerSelector',
                    'organizers',
                    'eventTypes',
                    'networkingModes',
                    'matchingStrategies',
                    'timezones',
                    'countries'
               )
          );
     }

     public function template(EventType $eventType) {
          if (!auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $eventType->load([
               'networkingMode:id,name',
               'matchingStrategy:id,name',
               'participantTypes' => function ($query) {
                    $query->select(
                         'participant_types.id',
                         'participant_types.name',
                         'participant_types.slug',
                         'participant_types.description',
                         'participant_types.icon',
                         'participant_types.color',
                         'participant_types.can_initiate_meetings',
                         'participant_types.can_receive_meetings',
                         'participant_types.priority_level',
                         'participant_types.max_daily_meetings',
                         'participant_types.is_active',
                         'event_type_participant_types.sort_order'
                    )->orderBy('event_type_participant_types.sort_order');
               },
               'matchRules',
          ]);

          return response()->json([
               'success' => true,
               'data' => $eventType,
          ]);
     }

     /**
     * Store Event.
     */
     public function store(Request $request) {
          if (!auth()->user()->canPermission('events.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               /*
               |--------------------------------------------------------------------------
               | Basic Information
               |--------------------------------------------------------------------------
               */
               'name'                  => ['required', 'string', 'max:255'],
               'event_type_id'         => ['required', Rule::exists('event_types', 'id')],
               'organizer_id'          => ['required', Rule::exists('organizers', 'id')],

               'description'           => ['nullable', 'string'],

               'venue'                 => ['nullable', 'string', 'max:255'],
               'address'               => ['nullable', 'string'],
               'city'                  => ['nullable', 'string', 'max:100'],
               'state'                 => ['nullable', 'string', 'max:100'],
               'country'               => ['nullable', 'string', 'max:100'],
               'postal_code'           => ['nullable', 'string', 'max:20'],

               'timezone'              => ['required', 'string', 'max:100'],

               'start_date'            => ['required', 'date'],
               'end_date'              => ['required', 'date', 'after_or_equal:start_date'],

               'logo'                  => ['nullable', 'image', 'max:2048'],
               'banner'                => ['nullable', 'image', 'max:5120'],


               /*
               |--------------------------------------------------------------------------
               | Event Configuration
               |--------------------------------------------------------------------------
               */
               'networking_mode_id'            => ['required', Rule::exists('networking_modes', 'id')],
               'matching_strategy_id'          => ['required', Rule::exists('matching_strategies', 'id')],

               'compatibility_engine_enabled'  => ['nullable', 'boolean'],
               'allow_overlapping_slots'       => ['nullable', 'boolean'],
               'allow_concurrent_meetings'     => ['nullable', 'boolean'],
               'meeting_approval_required'     => ['nullable', 'boolean'],

               'default_slot_duration'         => ['required', 'integer', 'min:5'],
               'default_slot_capacity'         => ['required', 'integer', 'min:1'],


               /*
               |--------------------------------------------------------------------------
               | Wizard JSON
               |--------------------------------------------------------------------------
               */
               'participant_types'     => ['required', 'json'],
               'match_rules'           => ['required', 'json'],
          ]);
          
          $participantTypes = json_decode($request->participant_types, true);
          $matchRules = json_decode($request->match_rules, true);

          DB::beginTransaction();

          try {
               $event = Event::create([
                    'organizer_id'                   => $validated['organizer_id'],
                    'event_type_id'                  => $validated['event_type_id'],

                    'networking_mode_id'             => $validated['networking_mode_id'],
                    'matching_strategy_id'           => $validated['matching_strategy_id'],

                    'name'                           => $validated['name'],
                    'slug'                           => Str::slug($validated['name']),
                    'description'                    => $validated['description'],

                    'status'                         => $request->status,

                    'start_date'                     => $validated['start_date'],
                    'end_date'                       => $validated['end_date'],
                    'booking_deadline'               => $request->booking_deadline,

                    'timezone'                       => $validated['timezone'],

                    'venue_name'                     => $request->venue_name,
                    'address'                        => $request->address,
                    'city'                           => $request->city,
                    'state'                          => $request->state,
                    'country'                        => $request->country,
                    'postal_code'                    => $request->postal_code,

                    'compatibility_engine_enabled'   => $request->boolean('compatibility_engine_enabled'),
                    'allow_overlapping_slots'        => $request->boolean('allow_overlapping_slots'),
                    'allow_concurrent_meetings'      => $request->boolean('allow_concurrent_meetings'),
                    'meeting_approval_required'      => $request->boolean('meeting_approval_required'),

                    'default_slot_duration'          => $validated['default_slot_duration'],
                    'default_slot_capacity'          => $validated['default_slot_capacity'],

                    'created_by'                     => auth()->id(),
               ]);

               /*
               |--------------------------------------------------------------------------
               | Logo
               |--------------------------------------------------------------------------
               */
               if ($request->hasFile('logo')) {
                    $event->update([
                         'logo' => $request->file('logo')->store(
                              "events/{$event->id}",
                              'public'
                         ),
                    ]);
               }

               /*
               |--------------------------------------------------------------------------
               | Banner
               |--------------------------------------------------------------------------
               */
               if ($request->hasFile('banner_image')) {
                    $event->update([
                         'banner_image' => $request->file('banner_image')->store(
                              "events/{$event->id}",
                              'public'
                         ),
                    ]);
               }

               /*
               |--------------------------------------------------------------------------
               | Step 2 : Event Participant Types
               |--------------------------------------------------------------------------
               */
               $participantTypeMap = [];

               foreach ($participantTypes as $participantType) {
                    $eventParticipantType = EventParticipantType::create([
                         'event_id'                 => $event->id,

                         'participant_type_id'      => $participantType['id'],

                         'name'                     => $participantType['name'],
                         'slug'                     => $participantType['slug'],
                         'description'              => $participantType['description'],

                         'icon'                     => $participantType['icon'],
                         'color'                    => $participantType['color'],

                         'can_initiate_meetings'    => $participantType['can_initiate_meetings'],
                         'can_receive_meetings'     => $participantType['can_receive_meetings'],

                         'priority_level'           => $participantType['priority_level'],
                         'max_daily_meetings'       => $participantType['max_daily_meetings'],

                         'sort_order'               => $participantType['sort_order'],
                         'is_active'                => $participantType['is_active'],

                         'created_by'               => auth()->id(),
                    ]);

                    $participantTypeMap[$participantType['id']] = $eventParticipantType->id;
               }

               /*
               |--------------------------------------------------------------------------
               | Step 3 : Event Match Rules
               |--------------------------------------------------------------------------
               */
               $rows = [];
               foreach ($matchRules as $matchRule) {
                    $rows[] = [
                         'event_id' => $event->id,

                         'source_event_participant_type_id' =>
                              $participantTypeMap[$matchRule['source_participant_type_id']],

                         'target_event_participant_type_id' =>
                              $participantTypeMap[$matchRule['target_participant_type_id']],

                         'priority_score' => $matchRule['priority_score'],

                         'is_match_allowed' => $matchRule['is_match_allowed'],
                         'auto_recommend' => $matchRule['auto_recommend'],
                         'visibility_enabled' => $matchRule['visibility_enabled'],

                         'created_by' => auth()->id(),

                         'created_at' => now(),
                         'updated_at' => now(),
                    ];
               }
               EventMatchRule::insert($rows);

               DB::commit();
               return response()->json([
                    'status' => 'success',
                    'message'  => 'Event created successfully.',
                    'redirect' => route('events.index'),
               ], 200);
          } catch (\Throwable $e) {
               DB::rollBack();
               report($e);
               return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create event.',
               ], 500);
          };
     }

     /**
     * Show Edit Event Form.
     */
     public function edit(Event $event): View {
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
               'event_management.events.edit',
               compact(
                    'event',
                    'eventTypes',
                    'networkingModes',
                    'matchingStrategies'
               )
          );
     }

     /**
     * Update Event.
     */
     public function update(Request $request, Event $event): RedirectResponse {
          abort_unless(
               auth()->user()->canPermission('events.edit'),
               403
          );

          $validated = $request->validate([
               'name' => ['required', 'string', 'max:255'],
               'description' => ['nullable', 'string'],

               'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
               'banner_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

               'start_date' => ['required', 'date'],
               'end_date' => ['required', 'date', 'after_or_equal:start_date'],
               'booking_deadline' => ['required', 'date', 'before_or_equal:start_date'],

               'timezone' => ['required', 'timezone'],

               'venue_name' => ['required', 'string', 'max:255'],
               'address' => ['required', 'string', 'max:500'],
               'city' => ['required', 'string', 'max:150'],
               'country' => ['required', 'string', 'max:150'],
          ]);

          /*
          |--------------------------------------------------------------------------
          | Logo
          |--------------------------------------------------------------------------
          */
          if ($request->hasFile('logo')) {
               if ($event->logo && Storage::disk('public')->exists($event->logo)) {
                    Storage::disk('public')->delete($event->logo);
               }

               $validated['logo'] = $request->file('logo')->store(
                    "events/{$event->id}",
                    'public'
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Banner
          |--------------------------------------------------------------------------
          */
          if ($request->hasFile('banner_image')) {
               if ($event->banner_image && Storage::disk('public')->exists($event->banner_image)) {
                    Storage::disk('public')->delete($event->banner_image);
               }

               $validated['banner_image'] = $request->file('banner_image')->store(
                    "events/{$event->id}",
                    'public'
               );
          }

          /*
          |--------------------------------------------------------------------------
          | Audit
          |--------------------------------------------------------------------------
          */
          $validated['updated_by'] = auth()->id();

          /*
          |--------------------------------------------------------------------------
          | Update Event
          |--------------------------------------------------------------------------
          */
          $event->update($validated);

          return redirect()
               ->route('events.overview.index', $event)
               ->with('success', 'Event updated successfully.');
     }

     /**
     * Delete Event.
     */
     public function destroy(Event $event) {
          if (!auth()->user()->canPermission('events.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Event Actions
     |--------------------------------------------------------------------------
     */
     public function publish(Event $event) {
          if (!auth()->user()->canPermission('events.publish')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          if ($event->status !== 'draft') {
               return response()->json(['success' => false,'message' => 'Only draft events can be published'], 422);
          }

          $event->update([
               'status' => 'published'
          ]);

          return response()->json(['success' => true,'message' => 'Event published successfully']);
     }

     /**
     * Archive Event.
     */
     public function archive(Event $event) {
          if (!auth()->user()->canPermission('events.archive')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
     }

     /**
     * Close Event.
     */
     public function close(Event $event) {
          if (!auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
     }

     public function createParticipantType() {
          if (!auth()->user()->canPermission('events.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
     
          $participantType = new ParticipantType();

          return view(
               'event_management.events.partials.participant_types.create',
               compact('participantType')
          );
     }

     public function storeParticipantType(Request $request) {
          if (!auth()->user()->canPermission('events.create')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'name'                   => ['required', 'string', 'max:255'],
               'description'            => ['nullable', 'string'],
               'priority_level'         => ['required', 'integer', 'min:1'],
               'max_daily_meetings'     => ['nullable', 'integer', 'min:0'],
               'sort_order'             => ['required', 'integer', 'min:0'],
               'can_initiate_meetings'  => ['nullable', 'boolean'],
               'can_receive_meetings'   => ['nullable', 'boolean'],
               'is_active'              => ['nullable', 'boolean'],
          ]);

          $participantType = [
               'id'                     => (string) Str::uuid(),
               'name'                   => $validated['name'],
               'description'            => $validated['description'],
               'priority_level'         => $validated['priority_level'],
               'max_daily_meetings'     => $validated['max_daily_meetings'],
               'sort_order'             => $validated['sort_order'],
               'can_initiate_meetings'  => $request->boolean('can_initiate_meetings'),
               'can_receive_meetings'   => $request->boolean('can_receive_meetings'),
               'is_active'              => $request->boolean('is_active'),
          ];

          return response()->json([
               'success'          => true,
               'message'          => 'Participant Type added successfully.',
               'callback'         => 'participantTypeStored',
               'participant_type' => $participantType,
          ]);
     }

     public function editParticipantType(ParticipantType $participantType) {
          if (!auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'event_management.events.partials.participant_types.edit',
               compact('participantType')
          );
     }

     public function updateParticipantType(Request $request, string $id) {
          if (!auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'name'                    => ['required', 'string', 'max:255'],
               'description'             => ['nullable', 'string'],
               'priority_level'          => ['required', 'integer', 'min:1'],
               'max_daily_meetings'      => ['nullable', 'integer', 'min:0'],
               'sort_order'              => ['required', 'integer', 'min:0'],
               'can_initiate_meetings'   => ['nullable', 'boolean'],
               'can_receive_meetings'    => ['nullable', 'boolean'],
               'is_active'               => ['nullable', 'boolean'],
          ]);

          $participantTypes = session('event_wizard.participant_types', []);

          foreach ($participantTypes as &$participantType) {

               if ($participantType['id'] !== $id) {
                    continue;
               }

               $participantType = array_merge($participantType, [
                    'name'                   => $validated['name'],
                    'description'            => $validated['description'],
                    'priority_level'         => $validated['priority_level'],
                    'max_daily_meetings'     => $validated['max_daily_meetings'],
                    'sort_order'             => $validated['sort_order'],
                    'can_initiate_meetings'  => $request->boolean('can_initiate_meetings'),
                    'can_receive_meetings'   => $request->boolean('can_receive_meetings'),
                    'is_active'              => $request->boolean('is_active'),
               ]);

               break;
          }

          session([
               'event_wizard.participant_types' => $participantTypes,
          ]);

          return response()->json([
               'success' => true,
               'message' => 'Participant Type updated successfully.',
               'callback' => 'participantTypeUpdated',

               'participant_type' => [
                    'id'                      => $id,
                    'name'                    => $validated['name'],
                    'description'             => $validated['description'],
                    'priority_level'          => $validated['priority_level'],
                    'max_daily_meetings'      => $validated['max_daily_meetings'],
                    'sort_order'              => $validated['sort_order'],
                    'can_initiate_meetings'   => $request->boolean('can_initiate_meetings'),
                    'can_receive_meetings'    => $request->boolean('can_receive_meetings'),
                    'is_active'               => $request->boolean('is_active'),
               ]
          ]);
     }

     public function destroyParticipantType(string $id) {
          if (!auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return response()->json([
               'success'  => true,
               'message'  => 'Participant Type deleted successfully.',
               'callback' => 'participantTypeDeleted',
               'id'       => $id,
          ]);
     }

     public function editMatchRule(EventTypeMatchRule $matchRule) {
          if (!auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view(
               'event_management.events.partials.match_rules.edit',
               compact('matchRule')
          );
     }

     public function updateMatchRule(Request $request, string $id) {
          if (!auth()->user()->canPermission('events.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }
          
          $validated = $request->validate([
               'priority_score'     => ['required', 'integer', 'min:0', 'max:100'],
               'is_match_allowed'   => ['nullable', 'boolean'],
               'auto_recommend'     => ['nullable', 'boolean'],
               'visibility_enabled' => ['nullable', 'boolean'],
          ]);

          return response()->json([
               'success'   => true,
               'message'   => 'Match Rule updated successfully.',
               'callback'  => 'matchRuleUpdated',

               'match_rule' => [
                    'id'                 => $id,
                    'priority_score'     => $validated['priority_score'],
                    'is_match_allowed'   => $request->boolean('is_match_allowed'),
                    'auto_recommend'     => $request->boolean('auto_recommend'),
                    'visibility_enabled' => $request->boolean('visibility_enabled'),
               ]
          ]);
     }
}