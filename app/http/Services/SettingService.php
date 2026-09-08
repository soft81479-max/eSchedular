<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingService {
     /**
      * Cache Prefix
      */
     protected string $cachePrefix = 'settings.';

     /*
     |--------------------------------------------------------------------------
     | Get Setting
     |--------------------------------------------------------------------------
     */
     public function get(string $key,mixed $default = null): mixed {
          return Cache::rememberForever(
               $this->cachePrefix.$key,
               function () use ($key, $default) {
                    $setting = Setting::query()
                         ->where('setting_key', $key)
                         ->first();

                    if (! $setting) {
                         return $default;
                    }

                    return $this->castValue(
                         $setting->setting_value,
                         $setting->setting_type
                    );
               }
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Set Setting
     |--------------------------------------------------------------------------
     */
     public function set(string $key,mixed $value): bool {
          $setting = Setting::query()
               ->where('setting_key', $key)
               ->first();

          if (! $setting) {
               return false;
          }

          $setting->update([
               'setting_value' => $value,
          ]);

          Cache::forget(
               $this->cachePrefix.$key
          );

          return true;
     }

     /*
     |--------------------------------------------------------------------------
     | Has Setting
     |--------------------------------------------------------------------------
     */
     public function has(string $key): bool {
          return Setting::query()
               ->where('setting_key', $key)
               ->exists();
     }

     /*
     |--------------------------------------------------------------------------
     | Forget Cache
     |--------------------------------------------------------------------------
     */
     public function forget(string $key): void {
          Cache::forget(
               $this->cachePrefix.$key
          );
     }

     /*
     |--------------------------------------------------------------------------
     | Clear All Settings Cache
     |--------------------------------------------------------------------------
     */
     public function clearCache(): void {
          $settings = Setting::query()->pluck('setting_key');

          foreach ($settings as $key) {
               Cache::forget(
                    $this->cachePrefix.$key
               );
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Get Group
     |--------------------------------------------------------------------------
     */
     public function group(string $group): Collection {
          return Setting::query()
               ->where(
                    'setting_group',
                    $group
               )
               ->orderBy('sort_order')
               ->get();
     }

     /*
     |--------------------------------------------------------------------------
     | Get All Grouped
     |--------------------------------------------------------------------------
     */
     public function all(): Collection {
          return Setting::query()
               ->orderBy('setting_group')
               ->orderBy('sort_order')
               ->get()
               ->groupBy('setting_group');
     }

     /*
     |--------------------------------------------------------------------------
     | Update Multiple
     |--------------------------------------------------------------------------
     */
     public function updateMany(array $settings): void {
          foreach ($settings as $key => $value) {
               Setting::query()
                    ->where(
                         'setting_key',
                         $key
                    )
                    ->update([
                         'setting_value' => $value,
                    ]);

               Cache::forget(
                    $this->cachePrefix.$key
               );
          }
     }

     /*
     |--------------------------------------------------------------------------
     | Cast Values
     |--------------------------------------------------------------------------
     */
     protected function castValue(mixed $value,string $type): mixed {
          return match ($type) {
               'boolean' => filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN
               ),

               'number' => is_numeric($value)
                    ? $value + 0
                    : 0,

               'json' => json_decode(
                    $value,
                    true
               ),

               default => $value,
          };
     }
}