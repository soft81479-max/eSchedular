<?php

namespace App\Services\Navigation;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SidebarResolver {
    public function resolve(User $user) {
        return Cache::remember(
            "sidebar:user:{$user->id}",
            now()->addMinutes(60),
            function () use ($user) {
                /*
                |--------------------------------------------------------------------------
                | Accessible Menu IDs
                |--------------------------------------------------------------------------
                */
                $menuIds = $user->roles()
                    ->join('role_menus', 'roles.id', '=', 'role_menus.role_id')
                    ->pluck('role_menus.menu_id')
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Include Parent Menus Automatically
                |--------------------------------------------------------------------------
                */
                $parentIds = Menu::query()
                    ->whereIn('id', $menuIds)
                    ->whereNotNull('parent_id')
                    ->pluck('parent_id');

                $allMenuIds = $menuIds
                    ->merge($parentIds)
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Fetch Menus
                |--------------------------------------------------------------------------
                */
                $menus = Menu::query()
                    ->select(
                        'id',
                        'name',
                        'slug',
                        'route',
                        'icon',
                        'parent_id',
                        'is_leaf',
                        'is_clickable',
                        'active_patterns',
                        'sort_order',
                        'route_key'
                    )
                    ->where('is_active', 1)
                    ->whereIn('id', $allMenuIds)
                    ->orderBy('sort_order')
                    ->get();

                return $this->buildTree($menus);
            }
        );
    }

    protected function buildTree($menus, $parentId = null) {
        return $menus
            ->where('parent_id', $parentId)
            ->map(function ($menu) use ($menus) {

                $menu->children = $this->buildTree(
                    $menus,
                    $menu->id
                )->values();

                return $menu;
            })
            ->filter(function ($menu) {
                /*
                |--------------------------------------------------------------------------
                | Hide Empty Parents
                |--------------------------------------------------------------------------
                */
                return $menu->is_leaf || $menu->children->isNotEmpty();
            })
            ->values();
    }

    public static function forget(User $user): void {
        Cache::forget(
            "sidebar:user:{$user->id}"
        );
    }
}