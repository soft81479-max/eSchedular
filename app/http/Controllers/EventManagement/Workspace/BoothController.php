<?php

namespace App\Http\Controllers\EventManagement\Workspace;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\Event;
use App\Models\EventBooth;
use App\Models\EventOrganization;
use App\Models\Organization;
use Illuminate\View\View;

class BoothController extends WorkspaceController {
     public function index(Event $event): View {
          return view(
               'event_management.workspace.booths.index',
               $this->workspace($event)
          );
     }

     public function datatable(Request $request, Event $event) {
          if (!$request->ajax()) {
               return response()->json(['success' => false,'message' => 'Unauthorized action'], 403);
          }
               
          if (!auth()->user()->canPermission('booths.view')) {
               return response()->json([
                    'draw' => intval($request->draw),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => []
               ]);
          }

          /*
          |--------------------------------------------------------------------------
          | Add Button
          |--------------------------------------------------------------------------
          */
          $addButtonHtml = '';

          if (auth()->user()->canPermission('booths.create')) {
               $addButtonHtml = action_button('add', [
                    'modal-size' => 'modal-md','label' => 'Generate','title' => 'Generate Booths','icon' => '','color' => 'warning',
                    'createUrl' => route('events.booths.createGenerateForm', $event),
                    'storeUrl' => route('events.booths.storeGenerate.store', $event),
                    'table' => 'boothsTable'
               ]);
          }
          
          $query = EventBooth::query()->with(['eventOrganization.organization',])->where('event_id', $event->id);

          return datatables()->eloquent($query)
               ->addColumn('code', function ($row) {
                    return sprintf('<span class="fw-semibold">%s</span>',e($row->code));
               })
               ->addColumn('name', function ($row) {
                    return e($row->name);
               })
               ->addColumn('type', function ($row) {
                    return sprintf('<span class="badge w-100 fs-xs bg-secondary">%s</span>',ucfirst($row->type));
               })
               ->addColumn('location', function ($row) {
                    return $row->location ?: '-';
               })
               ->addColumn('status', function ($row) {
                    $class = match ($row->status) {
                         EventBooth::STATUS_AVAILABLE => 'bg-primary',
                         EventBooth::STATUS_ASSIGNED => 'bg-success',
                         EventBooth::STATUS_RESERVED => 'bg-warning',
                         EventBooth::STATUS_INACTIVE => 'bg-danger',
                         default => 'bg-secondary',
                    };

                    return sprintf(
                         '<span class="badge fs-xs w-100 %s">%s</span>',
                         $class,
                         ucfirst($row->status)
                    );
               })
               ->addColumn('assigned_to', function ($row) {
                    if (! $row->eventOrganization) {
                         return '
                              <span class="badge border fs-xs bg-light w-100 text-muted">Free</span>
                         ';
                    }

                    $organization = $row->eventOrganization->organization;
                    return '
                         <div class="d-flex align-items-center">
                              <img src="' . e($organization->logo_url) . '" class="rounded-circle me-2 w-32px h-32px" style="object-fit:cover;">
                              <div style="line-height:1.14;">
                                   <p class="fw-semibold small mb-0">'. e($organization->name) .'</p>
                                   <span class="text-muted fs-xs">'. e(collect([$organization->city,$organization->country,])->filter()->implode(', ')) .'</span>
                              </div>
                         </div>
                    ';
               })
               ->addColumn('actions', function ($row) use ($event) {
                    $buttons = '';
                    if (auth()->user()->canPermission('booths.edit')) {
                         $buttons .= action_button('edit', [
                              'modal-size' => 'modal-md',
                              'btn-type' => 'btn-icon',
                              'label' => '',
                              'title' => 'Edit Booth',
                              'color' => 'primary',
                              'editUrl' => route('events.booths.edit',[$event, $row]),
                              'updateUrl' => route('events.booths.update',[$event, $row]),
                              'table' => 'boothsTable'
                         ]);
                    }
                    if (auth()->user()->canPermission('booths.edit') && $row->status !== EventBooth::STATUS_ASSIGNED) {
                         $buttons .= action_button('edit', [
                              'modal-size' => 'modal-md',
                              'btn-type' => 'btn-icon',
                              'label' => '',
                              'title' => 'Assign Booth',
                              'icon' => 'ph-link',
                              'color' => 'success',
                              'editUrl' => route('events.booths.assign',[$event, $row]),
                              'updateUrl' => route('events.booths.assign.update',[$event, $row]),
                              'table' => 'boothsTable'
                         ]);
                    }
                    if (auth()->user()->canPermission('booths.edit') && $row->status === EventBooth::STATUS_ASSIGNED) {
                         $buttons .= action_button('release', [
                              'btn-type' => 'btn-icon',
                              'label' => '',
                              'title' => 'Release Booth',
                              'color' => 'warning',
                              'icon' => 'ph-link-break',
                              'url' => route('events.booths.release',[$event, $row]),
                              'method' => 'DELETE',
                              'table' => 'boothsTable'
                         ]);
                    }

                    return $buttons;
               })
               ->rawColumns(['code','type','location','assigned_to','status','actions',])
               ->with('custom_button', [
                    'button_html' => $addButtonHtml,
               ])
               ->make(true);
     }

     public function createGenerateForm(Event $event) {
          if (! auth()->user()->canPermission('booths.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
               ], 403);
          }

          $alreadyGenerated = EventBooth::query()
               ->where('event_id', $event->id)
               ->exists();

          return view('event_management.workspace.booths.partials.generate-booth-form',compact('event', 'alreadyGenerated'));
    }

    public function storeGenerate(Request $request,Event $event) {
          $validated = $request->validate([
               'prefix'       => ['required', 'string', 'max:20'],
               'name_prefix'  => ['nullable', 'string', 'max:100'],
               'type'         => ['required', 'string'],
               'location'     => ['nullable', 'string', 'max:255'],
               'start'        => ['required', 'integer', 'min:1'],
               'end'          => ['required', 'integer', 'gte:start'],
               'padding'      => ['nullable', 'integer', 'min:1', 'max:10'],
          ]);

          $padding = $validated['padding'] ?? 3;

          $generated = 0;
          $skipped = 0;

          DB::transaction(function () use ($event,$validated,$padding,&$generated,&$skipped) {
               for ($i = $validated['start']; $i <= $validated['end']; $i++) {
                    $number = str_pad($i,$padding,'0',STR_PAD_LEFT);
                    $code = sprintf('%s-%s',strtoupper($validated['prefix']),$number);

                    $exists = EventBooth::query()
                         ->where('event_id', $event->id)
                         ->where('code', $code)
                         ->exists();

                    if ($exists) {
                         $skipped++;
                         continue;
                    }

                    EventBooth::create([
                         'event_id' => $event->id,
                         'code' => $code,
                         'name' => trim(
                              ($validated['name_prefix']
                              ?: ucfirst($validated['type']))
                              .' '.$number
                         ),
                         'type' => strtolower(
                              $validated['type']
                         ),
                         'location' => $validated['location'],
                         'status' => EventBooth::STATUS_AVAILABLE,
                    ]);
                    $generated++;
               }
          });

          return response()->json([
               'success' => true,
               'message' => sprintf(
                    '%d booth(s) generated. %d skipped.',
                    $generated,
                    $skipped
               ),
               'generated' => $generated,
               'skipped' => $skipped,
          ]);
     }

     public function edit(Event $event,EventBooth $booth) {
          if (! auth()->user()->canPermission('booths.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
               ], 403);
          }
          
          abort_unless(
               $booth->event_id == $event->id,
               404
          );

          return view('event_management.workspace.booths.partials.form',compact('event','booth'));
     }

     public function update(Request $request,Event $event,EventBooth $booth) {
          if (! auth()->user()->canPermission('booths.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
               ], 403);
          }
          
          abort_unless(
               $booth->event_id == $event->id,
               404
          );

          $validated = $request->validate([
               'code' => [
                    'required','string','max:50',
                    Rule::unique('event_booths')
                         ->where(function ($query) use ($event) {
                              return $query->where('event_id',$event->id);
                         })
                         ->ignore($booth->id),
               ],
               'name' => [
                    'required','string','max:255',
               ],
               'type' => [
                    'required',
                    Rule::in(['booth','table','kiosk','desk',]),
               ],
               'location' => ['nullable','string','max:255',],
               'status' => [
                    'required',
                    Rule::in([
                         EventBooth::STATUS_AVAILABLE,
                         EventBooth::STATUS_ASSIGNED,
                         EventBooth::STATUS_RESERVED,
                         EventBooth::STATUS_INACTIVE,
                    ]),
               ],
          ]);

          DB::beginTransaction();
          try {
               /*
               |--------------------------------------------------------------------------
               | Prevent accidental status corruption
               |--------------------------------------------------------------------------
               */
               if ($booth->event_organization_id && $validated['status'] === EventBooth::STATUS_AVAILABLE) {
                    return back()
                         ->withErrors([
                              'status' => 'Assigned booths cannot be marked available. Please release the booth first.',
                         ])
                         ->withInput();
               }

               $booth->update($validated);
               DB::commit();
               return response()->json(['success' => true,'message' => 'Booth updated successfully']);
          } catch (\Throwable $e) {
               DB::rollBack();
               return response()->json(['success' => false,'message' => 'Failed to update booth'], 500);
          }
     }

     public function assign(Event $event,EventBooth $booth) {
          if (! auth()->user()->canPermission('booths.edit')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
               ], 403);
          }

          abort_unless(
               $booth->event_id == $event->id,
               404
          );

          $eventOrganizations = EventOrganization::query()
               ->with([
                    'organization',
                    'participantType',
               ])
               ->where('event_id', $event->id)
               ->where(function ($query) use ($booth) {
                    $query->whereDoesntHave('booth');
                    if ($booth->event_organization_id) {
                         $query->orWhere(
                              'id',
                              $booth->event_organization_id
                         );
                    }
               })
               ->orderBy(
                    Organization::select('name')
                         ->whereColumn(
                              'organizations.id',
                              'event_organizations.organization_id'
                         )
               )
               ->get();

          return view(
               'event_management.workspace.booths.partials.assign',
               compact(
                    'event',
                    'booth',
                    'eventOrganizations'
               )
          );
     }

     public function assignUpdate(Request $request,Event $event,EventBooth $booth) {
          abort_unless(
               $booth->event_id == $event->id,
               404
          );

          $validated = $request->validate([
               'event_organization_id' => ['required','exists:event_organizations,id',],
          ]);

          $eventOrganization = EventOrganization::query()
               ->where('event_id', $event->id)
               ->findOrFail(
                    $validated['event_organization_id']
               );

          $booth->update([
               'event_organization_id' => $eventOrganization->id,
               'status' => EventBooth::STATUS_ASSIGNED,
          ]);

          return response()->json([
               'success' => true,
               'message' => sprintf('%s assigned successfully.',$booth->code),
          ]);
     }

     public function release(Event $event,EventBooth $booth) {          
          if (! auth()->user()->canPermission('booths.delete')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action'
               ], 403);
          }

          abort_unless(
               $booth->event_id === $event->id,
               404
          );

          /*
          |--------------------------------------------------------------------------
          | Already Released
          |--------------------------------------------------------------------------
          */
          if (! $booth->event_organization_id) {
               return response()->json([
                    'success' => false,
                    'message' => 'Booth is already available.',
               ], 422);
          }

          /*
          |--------------------------------------------------------------------------
          | Release Booth
          |--------------------------------------------------------------------------
          */
          $booth->update([
               'event_organization_id' => null,
               'status' => EventBooth::STATUS_AVAILABLE,
          ]);

          return response()->json([
               'success' => true,
               'message' => sprintf('%s has been released successfully.',$booth->code),
          ]);
     }
}