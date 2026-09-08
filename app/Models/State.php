<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model {
     protected $table = 'states';
     protected $primaryKey = 'state_id';

     protected $fillable = [
          'country_id', 'state_code', 'name', 'is_active'
     ];

     public function country() {
          return $this->belongsTo(Country::class, 'country_id', 'country_id');
     }

     public function cities() {
          return $this->hasMany(City::class, 'state_id', 'state_id');
     }
}