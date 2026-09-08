<?php

namespace App\Services\Navigation;

use App\Models\Menu;
use Illuminate\Support\Str;

class BreadcrumbResolver {
     /**
     * Manual overrides for special routes.
     */
     protected array $overrides = [
          /*
          |--------------------------------------------------------------------------
          | Examples
          |--------------------------------------------------------------------------
          |
          | 'meetings.reschedule' => [
          |     'title'       => 'Reschedule Meeting',
          |     'description' => 'Update an existing meeting schedule.',
          |     'breadcrumbs' => [
          |         ['label' => 'Dashboard', 'route' => 'dashboard'],
          |         ['label' => 'Networking', 'route' => null],
          |         ['label' => 'Meetings', 'route' => 'meetings.index'],
          |         ['label' => 'Reschedule', 'route' => null],
          |     ],
          | ],
          |
          */
     ];

     /**
     * Friendly CRUD action titles.
     */
     protected array $actionTitles = [
          'index'   => 'View',
          'show'    => 'View',
          'create'  => 'Create',
          'edit'    => 'Edit',
          'profile' => 'Profile',
     ];

     /**
     * Resolve page title, description and breadcrumbs.
     */
     public function resolve(?string $routeName = null): array {
          $routeName ??= request()->route()?->getName();

          /*
          |--------------------------------------------------------------------------
          | No Route
          |--------------------------------------------------------------------------
          */
          if (empty($routeName)) {
               return [
                    'title'       => '',
                    'description' => '',
                    'breadcrumbs' => [],
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | Manual Overrides
          |--------------------------------------------------------------------------
          */
          if (isset($this->overrides[$routeName])) {
               return $this->overrides[$routeName];
          }

          /*
          |--------------------------------------------------------------------------
          | Exact Menu Match
          |--------------------------------------------------------------------------
          */
          $menu = Menu::query()
               ->where('route_key', $routeName)
               ->first();

          if ($menu) {
               return [
                    'title'       => $menu->name,
                    'description' => $menu->description ?? '',
                    'breadcrumbs' => $this->buildMenuBreadcrumbs($menu),
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | CRUD / Nested Route Convention
          |--------------------------------------------------------------------------
          */

          return $this->resolveCrudBreadcrumb($routeName);
     }

     /**
     * Build breadcrumbs using menu hierarchy.
     */
     protected function buildMenuBreadcrumbs(Menu $menu): array {
          $breadcrumbs = [];

          while ($menu) {
               $breadcrumbs[] = [
                    'label' => $menu->name,
                    'route' => $menu->route_key,
               ];

               $menu = $menu->parent;
          }

          $breadcrumbs = array_reverse($breadcrumbs);

          /*
          |--------------------------------------------------------------------------
          | Prevent Duplicate Dashboard
          |--------------------------------------------------------------------------
          */
          if (empty($breadcrumbs) || ($breadcrumbs[0]['route'] ?? null) !== 'dashboard') {
               array_unshift($breadcrumbs, [
                    'label' => 'Dashboard',
                    'route' => 'dashboard',
               ]);
          }

          return $breadcrumbs;
     }

     /**
     * Resolve CRUD and nested routes.
     */
     protected function resolveCrudBreadcrumb(string $routeName): array {
          $segments = explode('.', $routeName);

          /*
          |--------------------------------------------------------------------------
          | No Convention Match
          |--------------------------------------------------------------------------
          */
          if (count($segments) < 2) {
               return [
                    'title'       => Str::headline(
                         str_replace('.', ' ', $routeName)
                    ),
                    'description' => '',
                    'breadcrumbs' => [
                         [
                         'label' => 'Dashboard',
                         'route' => 'dashboard',
                         ],
                    ],
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | Determine Action
          |--------------------------------------------------------------------------
          */
          $action = array_pop($segments);
          $actionTitle = $this->actionTitles[$action] ?? Str::headline($action);

          /*
          |--------------------------------------------------------------------------
          | Find Closest Menu Route
          |--------------------------------------------------------------------------
          */
          $menu = null;
          $lookupSegments = $segments;

          while (! empty($lookupSegments)) {
               $candidate = implode('.', $lookupSegments);
               $menu = Menu::query()
                    ->where('route_key', "{$candidate}.index")
                    ->first();

               if ($menu) {
                    break;
               }

               array_pop($lookupSegments);
          }

          /*
          |--------------------------------------------------------------------------
          | Menu Found
          |--------------------------------------------------------------------------
          */
          if ($menu) {
               $breadcrumbs = $this->buildMenuBreadcrumbs($menu);
               $breadcrumbs[] = [
                    'label' => $actionTitle,
                    'route' => null,
               ];

               return [
                    'title'       => "{$actionTitle} {$menu->name}",
                    'description' => $menu->description ?? '',
                    'breadcrumbs' => $breadcrumbs,
               ];
          }

          /*
          |--------------------------------------------------------------------------
          | Absolute Fallback
          |--------------------------------------------------------------------------
          */
          return [
               'title'       => $actionTitle,
               'description' => '',
               'breadcrumbs' => [
                    [
                         'label' => 'Dashboard',
                         'route' => 'dashboard',
                    ],
                    [
                         'label' => Str::headline(
                         implode(' ', $segments)
                         ),
                         'route' => null,
                    ],
                    [
                         'label' => $actionTitle,
                         'route' => null,
                    ],
               ],
          ];
     }
}