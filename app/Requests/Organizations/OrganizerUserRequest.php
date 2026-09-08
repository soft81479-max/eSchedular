<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class OrganizerUserRequest extends FormRequest {
     /**
      * Determine if the user is authorized.
      */
     public function authorize(): bool
     {
          return true;
     }

     /**
      * Validation rules.
      */
     public function rules(): array {
          $organizerUser = $this->route('organizerUser');
          $user = $organizerUser?->user;

          return [
               /*
               |--------------------------------------------------------------------------
               | User Information
               |--------------------------------------------------------------------------
               */

               'name' => [
                    'required',
                    'string',
                    'max:255',
               ],

               'email' => [
                    'required',
                    'email',
                    'max:255',

                    Rule::unique(
                         'users',
                         'email'
                    )->ignore(
                         $user?->id
                    ),
               ],

               'phone' => [
                    'required',
                    'string',
                    'max:50',

                    Rule::unique(
                         'users',
                         'phone'
                    )->ignore(
                         $user?->id
                    ),
               ],

               'timezone' => [
                    'required',
                    'string',
                    'max:100',
               ],

               'avatar' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:2048',
               ],

               /*
               |--------------------------------------------------------------------------
               | Password
               |--------------------------------------------------------------------------
               */

               'password' => [
                    $this->isMethod('post')
                         ? 'required'
                         : 'nullable',

                    'confirmed',
                    'min:8',
               ],

               /*
               |--------------------------------------------------------------------------
               | Status
               |--------------------------------------------------------------------------
               */

               'status' => [
                    'required',
                    Rule::in([
                         'active',
                         'inactive',
                    ]),
               ],
          ];
     }

     /**
      * Custom messages.
      */
     public function messages(): array {
          return [
               'name.required' => 'Name is required.',

               'email.required' => 'Email is required.',
               'email.email'    => 'Please enter a valid email address.',

               'phone.required' => 'Phone is required.',

               'timezone.required' => 'Timezone is required.',

               'password.required' => 'Password is required.',
               'password.confirmed' => 'Password confirmation does not match.',
               'password.min' => 'Password must be at least 8 characters.',

               'status.required' => 'Status is required.',
          ];
     }
}