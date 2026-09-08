<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest {
     /**
     * Authorize
     */
     public function authorize(): bool
     {
          return auth()->check();
     }

     /**
     * Validation Rules
     */
     public function rules(): array {
          return [
               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               'organizer_id' => [
                    Rule::requiredIf(
                         auth()->user()->hasRole([
                              'super_admin',
                              'admin',
                         ])
                    ),
                    'sometimes',
                    'exists:organizers,id',
               ],

               'name' => [
                    'required',
                    'string',
                    'max:255',
               ],

               'organization_type_id' => [
                    'required',
                    'exists:organization_types,id',
               ],

               'logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
               ],

               'website' => [
                    'nullable',
                    'url',
                    'max:255',
               ],

               'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('organizations', 'email')->ignore(
                         $this->route('organization')
                    ),
               ],

               'phone' => [
                    'required',
                    'string',
                    'max:25',
               ],

               'address' => [
                    'required',
                    'string',
                    'max:1000',
               ],

               'country_id' => [
                    'required',
                    'exists:countries,country_id',
               ],

               'state_id' => [
                    'required',
                    'exists:states,state_id',
               ],

               'city_id' => [
                    'required',
                    'exists:cities,city_id',
               ],

               'description' => [
                    'nullable',
                    'string',
               ],

               'status' => [
                    'required',
                    Rule::in([
                    'active',
                    'inactive',
                    'pending',
                    'rejected',
                    ]),
               ],

               /*
               |--------------------------------------------------------------------------
               | Organization Administrator
               |--------------------------------------------------------------------------
               */
               'avatar' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
               ],

               'admin_name' => [
                    'required',
                    'string',
                    'max:255',
               ],

               'admin_email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore(
                         $this->route('organization')?->owner_user_id
                    ),
               ],

               'admin_phone' => [
                    'required',
                    'string',
                    'max:25',
               ],

               'password' => [
                    Rule::requiredIf($this->isMethod('post')),
                    'nullable',
                    'confirmed',
                    'min:8',
               ],

               'timezone' => [
                    'required',
                    Rule::in(\DateTimeZone::listIdentifiers()),
               ],

               'admin_status' => [
                    'required',
                    Rule::in([
                    'active',
                    'inactive',
                    ]),
               ],
          ];
     }

     /**
     * Custom Messages
     */
     public function messages(): array {
          return [

               '*.required' => 'The :attribute field is required.',
               '*.email'    => 'Please enter a valid :attribute.',
               '*.unique'   => 'The :attribute has already been taken.',
               '*.exists'   => 'The selected :attribute is invalid.',
               '*.image'    => 'The :attribute must be an image.',
               '*.url'      => 'Please enter a valid URL.',
               '*.confirmed'=> 'The password confirmation does not match.',
               '*.max'      => 'The :attribute may not be greater than :max characters.',
               '*.min'      => 'The :attribute must be at least :min characters.',
          ];
     }

     /**
     * Friendly Attribute Names
     */
     public function attributes(): array {
          return [
               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */
               'organizer_id'         => 'organizer',
               'name'                 => 'organization name',
               'organization_type_id' => 'organization type',
               'logo'                 => 'organization logo',
               'website'              => 'website',
               'email'                => 'organization email',
               'phone'                => 'organization phone',
               'address'              => 'address',
               'country_id'           => 'country',
               'state_id'             => 'state',
               'city_id'              => 'city',
               'description'          => 'description',
               'status'               => 'organization status',

               /*
               |--------------------------------------------------------------------------
               | Organization Administrator
               |--------------------------------------------------------------------------
               */
               'avatar'      => 'administrator avatar',
               'admin_name'   => 'administrator name',
               'admin_email'  => 'administrator email',
               'admin_phone'  => 'administrator phone',
               'password'    => 'password',
               'timezone'    => 'timezone',
               'admin_status' => 'administrator status',
          ];
     }
}