<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model {
     protected $table = 'countries';
     protected $primaryKey = 'country_id';
     public $incrementing = false;
     protected $keyType = 'string';

     protected $fillable = [
          'country_id', 'name', 'web_code', 'region', 'continent', 'is_active'
     ];

     public function states() {
          return $this->hasMany(State::class, 'country_id', 'country_id');
     }

     public function cities() {
          return $this->hasMany(City::class, 'country_id', 'country_id');
     }
}