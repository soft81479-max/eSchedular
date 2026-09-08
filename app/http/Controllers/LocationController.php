<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\State;
use App\Models\City;

class LocationController extends Controller {
     public function getCountries() {
          return Country::select('country_id', 'name')->where('is_active', 1)->orderBy('name')->get();
     }

     public function getStates($country_id) {
          return State::select('state_id', 'name')->where('country_id', $country_id)->where('is_active', 1)->orderBy('name')->get();
     }

     public function getCities($state_id) {
          return City::select('city_id', 'name')->where('state_id', $state_id)->where('is_active', 1)->orderBy('name')->get();
     }
}