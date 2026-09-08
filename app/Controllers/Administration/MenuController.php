<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class MenuController extends Controller {
     /*
     |--------------------------------------------------------------------------
     | Index
     |--------------------------------------------------------------------------
     */
     public function index(): View {
          if (!auth()->user()->canPermission('menus.view')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          return view('administration.menus.index');
     }

     /*
     |--------------------------------------------------------------------------
     | Datatable
     |--------------------------------------------------------------------------
     */
     public function datatable(Request $request): JsonResponse {
          if ($request->ajax()) {
               if (!auth()->user()->canPermission('menus.view')) {
                    return response()->json([
                         'success' => false,
                         'message' => 'Permission denied'
                    ], 403);
               }

               $addButtonHtml = '';
               $selectStatusFilter  = '';

               if (auth()->user()->canPermission('menus.view')) {
                    $addButtonHtml = action_button('add', [
                         'modal-size' => 'modal-lg','label' => 'Add','title' => 'Add Module','icon' => '','color' => 'warning',
                         'createUrl' => route('menus.create'),
                         'storeUrl' => route('menus.store'),
                         'table' => 'menusTable'
                    ]);

                    $selectStatusFilter = '
                         <select id="status" class="form-select form-select-sm">
                              <option value="">All Statuses</option>
                              <option value="1">Active</option>
                              <option value="0">Inactive</option>
                         </select>
                    ';
               }

               $query = Menu::query()->with('parent')->withCount('children')->orderBy('sort_order')->orderByRaw('COALESCE(parent_id, id)')->orderBy('name');
               if ($request->filled('status')) {
                    $query->where(
                         'is_active',
                         (int) $request->status
                    );
               }

               $query->where(function ($q) {
                    $q->whereNotNull('parent_id')
                    ->orWhereDoesntHave('children');
               });

               return DataTables::eloquent($query)
                    ->addIndexColumn()
                    /*
                    |--------------------------------------------------------------------------
                    | Group
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('group_name', function ($row) {
                         /*
                         |--------------------------------------------------------------------------
                         | Child Menu
                         |--------------------------------------------------------------------------
                         */
                         if ($row->parent_id) {
                              return $row->parent->name;
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Parent Menu
                         |--------------------------------------------------------------------------
                         */
                         if ($row->children_count > 0) {
                              return $row->name;
                         }

                         /*
                         |--------------------------------------------------------------------------
                         | Standalone Menu
                         |--------------------------------------------------------------------------
                         */
                         return $row->name;
                    })
                    ->addColumn('is_groupable', function ($row) {
                         return $row->children_count > 0 || $row->parent_id !== null;
                    })
                    ->addColumn('is_parent_menu', function ($row) {
                         return is_null($row->parent_id) && $row->children_count > 0;
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Menu
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('menu_name', function ($row) {
                         // Parent menu
                         if (is_null($row->parent_id) && $row->children_count > 0) {
                              return '';
                         }

                         // Standalone menu
                         if (is_null($row->parent_id) && $row->children_count === 0) {
                              return '<div class="d-flex align-items-center ms-2">'.$row->name.'</div>';
                         }

                         // Child menu
                         return '<div class="d-flex align-items-center ms-2">'.$row->name.'</div>';
                    })
                    ->addColumn('menu_desp', function ($row) {
                         return $row->description;
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Route
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('route_name', function ($row) {
                         return $row->route
                              ? '<span class="badge fs-xs bg-light text-body border">'.$row->route_key.'</span>'
                              : '<span class="badge fs-xs bg-secondary">N/A</span>';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('menu_status', function ($row) {
                         return $row->is_active
                              ? '<span class="badge fs-xs bg-success">Active</span>'
                              : '<span class="badge fs-xs bg-secondary">Inactive</span>';
                    })

                    /*
                    |--------------------------------------------------------------------------
                    | Actions
                    |--------------------------------------------------------------------------
                    */
                    ->addColumn('actions', function ($row) {
                         $actions = '';

                         if (canPermission('menus.view')) {
                              $actions .= '<a href="#" class="dropdown-item" data-action="viewModal" data-title="View Menus" data-bs-toggle="modal" data-bs-target="#modal-dialog" 
                                   data-modal-size="modal-lg" data-table-reload="permissionsTable" data-url="'.route('menus.show', $row).'">
                                   <i class="ph-eye me-2"></i>
                                   View Menus
                              </a>';
                         }

                         if (canPermission('menus.manage')) {
                              $actions .= '<a href="#" class="dropdown-item" data-action="edit" data-title="Edit Menus" data-bs-toggle="modal" data-bs-target="#modal-dialog" 
                                   data-modal-size="modal-lg" data-table-reload="permissionsTable" data-edit-url="'.route('menus.edit', $row).'" data-update-url="'.route('menus.update', $row).'">
                                   <i class="ph-pencil-line me-2"></i>
                                   Edit Menus
                              </a>';
                         }

                         return '
                              <div class="dropdown">
                                   <a href="#" class="text-body" data-bs-toggle="dropdown"><i class="ph-list"></i></a>
                                   <div class="dropdown-menu dropdown-menu-end">
                                        '.$actions.'
                                   </div>
                              </div>
                         ';
                    })
                    ->rawColumns(['group_name','menu_name','menu_desp','route_name','menu_status','actions',])
                    ->with('custom_meta', [
                         'button_html' => $addButtonHtml,
                    ])
                    ->with('statusFilter', [
                         'status_filter' => $selectStatusFilter,
                    ])
                    ->make(true);
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Create
     |--------------------------------------------------------------------------
     */
     public function create(): View {
          if (!auth()->user()->canPermission('menus.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $menus = Menu::query()
               ->where('is_leaf', false)
               ->where('is_active', true)
               ->orderBy('name')
               ->get();

          return view('administration.menus.partials.create', [
               'menus' => $menus,
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Store
     |--------------------------------------------------------------------------
     */
     public function store(Request $request): JsonResponse {
          if (!auth()->user()->canPermission('menus.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'name'            => ['required', 'string', 'max:255'],
               'description'     => ['nullable', 'string', 'max:1000'],
               'slug'            => ['required', 'string', 'max:100', 'unique:menus,slug'],
               'route'           => ['nullable', 'string', 'max:255'],
               'route_key'       => ['nullable', 'string', 'max:255', 'unique:menus,route_key'],
               'icon'            => ['nullable', 'string', 'max:100'],
               'parent_id'       => ['nullable', 'exists:menus,id'],
               'active_patterns' => ['nullable', 'string', 'max:1000'],
               'sort_order'      => ['required', 'integer', 'min:0'],
               'is_leaf'         => ['required', 'boolean'],
               'is_clickable'    => ['required', 'boolean'],
               'is_active'       => ['required', 'boolean'],
          ]);

          Menu::create($validated);

          return response()->json([
               'success' => true,
               'message' => 'Menu created successfully.',
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Show
     |--------------------------------------------------------------------------
     */
     public function show(Menu $menu): View {
          if (!auth()->user()->canPermission('menus.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $menu->loadCount('children');

          return view('administration.menus.partials.show', [
               'menu' => $menu->load('parent'),
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Edit
     |--------------------------------------------------------------------------
     */
     public function edit(Menu $menu): View {
          if (!auth()->user()->canPermission('menus.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $menus = Menu::query()
               ->where('id', '!=', $menu->id)
               ->where('is_leaf', false)
               ->where('is_active', true)
               ->orderBy('name')
               ->get();

          return view('administration.menus.partials.edit', [
               'menu'  => $menu,
               'menus' => $menus,
          ]);
     }

     /*
     |--------------------------------------------------------------------------
     | Update
     |--------------------------------------------------------------------------
     */
     public function update(Request $request, Menu $menu): JsonResponse {
          if (!auth()->user()->canPermission('menus.manage')) {
               return response()->json([
                    'success' => false,
                    'message' => 'Permission denied'
               ], 403);
          }

          $validated = $request->validate([
               'name'            => ['required', 'string', 'max:255'],
               'description'     => ['nullable', 'string', 'max:1000'],
               'slug'            => ['required', 'string', 'max:100', 
                    Rule::unique('menus', 'slug')->ignore($menu->id)],
               'route'           => ['nullable', 'string', 'max:255'],
               'route_key'       => ['nullable', 'string', 'max:255', 
                    Rule::unique('menus', 'route_key')->ignore($menu->id)],
               'icon'            => ['nullable', 'string', 'max:100'],
               'parent_id'       => ['nullable', 'exists:menus,id'],
               'active_patterns' => ['nullable', 'string', 'max:1000'],
               'sort_order'      => ['required', 'integer', 'min:0'],
               'is_leaf'         => ['required', 'boolean'],
               'is_clickable'    => ['required', 'boolean'],
               'is_active'       => ['required', 'boolean'],
          ]);

          if ($validated['parent_id'] == $menu->id) {
               return response()->json([
                    'success' => false,
                    'message' => 'Menu cannot be its own parent.',
               ], 422);
          }

          //if (strtolower($role->name) === 'super_admin') {
          //     return response()->json([
          //          'success' => false,
          //          'message' => 'Administrator menus cannot be modified.'
          //     ], 422);
          //}

          $menu->update($validated);

          return response()->json([
               'success' => true,
               'message' => 'Menu updated successfully.',
          ]);
     }
}