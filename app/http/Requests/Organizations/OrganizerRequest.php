<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class OrganizerRequest extends FormRequest {
    /**
     * Determine if the user is authorized.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array {
        $organizer = $this->route('organizer');
        $user = $organizer?->primary_user;

        return [

            /*
            |--------------------------------------------------------------------------
            | Organizer Information
            |--------------------------------------------------------------------------
            */

            'name' => [
                'required',
                'string',
                'max:255',
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
                Rule::unique('organizers', 'email')
                    ->ignore($organizer?->id),
            ],

            'phone' => [
                'required',
                'string',
                'max:50',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'banner_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */

            'address_line_1' => [
                'required',
                'string',
                'max:255',
            ],

            'address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'country_id' => [
                'required',
            ],

            'state_id' => [
                'required',
            ],

            'city_id' => [
                'required',
            ],

            'pincode' => [
                'required',
                'string',
                'max:20',
            ],

            /*
            |--------------------------------------------------------------------------
            | Compliance
            |--------------------------------------------------------------------------
            */

            'gst_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'pan_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'registration_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Primary User (Create Only)
            |--------------------------------------------------------------------------
            */

            'user_name' => [
                'required',
                'string',
                'max:255',
            ],

            'user_email' => [
                'required',
                'email',
                'max:255',

                Rule::unique('users', 'email')
                    ->ignore($user?->id),
            ],

            'user_phone' => [
                'required',
                'string',
                'max:50',

                Rule::unique('users', 'phone')
                    ->ignore($user?->id),
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

            'password' => [
                $this->isMethod('post')
                    ? 'required'
                    : 'nullable',

                'confirmed',
                'min:8',
            ],

            'user_status' => [
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
            'name.required' => 'Organizer name is required.',
            'email.required' => 'Organizer email is required.',
            'phone.required' => 'Organizer phone is required.',

            'address_line_1.required' => 'Address line 1 is required.',
            'country_id.required' => 'Country is required.',
            'state_id.required' => 'State is required.',
            'city_id.required' => 'City is required.',
            'pincode.required' => 'Pincode is required.',

            'user_name.required' => 'Primary user name is required.',
            'user_email.required' => 'Primary user email is required.',
            'user_phone.required' => 'Primary user phone is required.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'timezone.required' => 'Timezone is required.',
        ];
    }
}