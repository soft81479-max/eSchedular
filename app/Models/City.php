<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model {
     protected $table = 'cities';
     protected $primaryKey = 'city_id';

     protected $fillable = [
          'state_id', 'country_id', 'name', 'is_active'
     ];

     public function state() {
          return $this->belongsTo(State::class, 'state_id', 'state_id');
     }

     public function country() {
          return $this->belongsTo(Country::class, 'country_id', 'country_id');
     }
}