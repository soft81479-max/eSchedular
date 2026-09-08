<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationUserRequest extends FormRequest
{
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
     public function rules(): array
     {
          $user = $this->route('organizationUser')?->user;

          return [

               /*
               |--------------------------------------------------------------------------
               | User
               |--------------------------------------------------------------------------
               */

               'avatar' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
               ],

               'name' => [
                    'required',
                    'string',
                    'max:255',
               ],

               'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')
                         ->ignore($user?->id),
               ],

               'phone' => [
                    'required',
                    'string',
                    'max:25',
               ],

               'designation' => [
                    'required',
                    'string',
                    'max:255',
               ],

               'bio' => [
                    'required',
                    'string',
                    'max:2000',
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

               'status' => [
                    'required',
                    Rule::in([
                         'active',
                         'inactive',
                    ]),
               ],

               /*
               |--------------------------------------------------------------------------
               | Organization
               |--------------------------------------------------------------------------
               */

               'is_admin' => [
                    'required',
                    'boolean',
               ],
          ];
     }

     /**
      * Custom Messages
      */
     public function messages(): array
     {
          return [

               '*.required'        => 'The :attribute field is required.',
               '*.email'           => 'Please enter a valid :attribute.',
               '*.unique'          => 'The :attribute has already been taken.',
               '*.image'           => 'The :attribute must be an image.',
               '*.confirmed'       => 'The password confirmation does not match.',
               '*.max'             => 'The :attribute may not be greater than :max characters.',
               '*.min'             => 'The :attribute must be at least :min characters.',
          ];
     }

     /**
      * Friendly Attribute Names
      */
     public function attributes(): array
     {
          return [

               'avatar'       => 'avatar',
               'name'         => 'name',
               'email'        => 'email',
               'phone'        => 'phone',
               'password'     => 'password',
               'timezone'     => 'timezone',
               'status'       => 'status',
               'designation'  => 'designation',
               'bio'          => 'bio',
               'is_admin'     => 'user type',
          ];
     }
}