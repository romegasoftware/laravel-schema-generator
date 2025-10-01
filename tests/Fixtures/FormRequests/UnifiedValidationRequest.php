<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema(name: 'UnifiedValidationRequestSchema')]
class UnifiedValidationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'auth_type' => ['required', 'in:password,otp'],

            'credentials.email' => ['required', 'email', 'max:255'],
            'credentials.password' => ['required_if:auth_type,password', 'nullable', 'string', 'min:8'],
            'credentials.otp' => ['required_if:auth_type,otp', 'nullable', 'digits:6'],

            'profile.name' => ['required', 'string', 'max:150'],
            'profile.bio' => ['nullable', 'string', 'max:500'],
            'profile.website' => ['nullable', 'url'],
            'profile.timezone' => ['required', 'string'],

            'profile.preferences.accepted_terms' => ['accepted'],
            'profile.preferences.tags' => ['array', 'min:1', 'max:5', 'distinct'],
            'profile.preferences.tags.*' => ['string', 'in:news,updates,offers'],

            'profile.contacts' => ['array', 'min:1'],
            'profile.contacts.*.email' => ['required', 'email'],
            'profile.contacts.*.phone' => ['nullable', 'string'],
            'profile.contacts.*.label' => ['required', 'string', 'in:primary,backup'],

            'profile.address.street' => ['required', 'string'],
            'profile.address.city' => ['required', 'string'],
            'profile.address.postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'profile.address.country' => ['required', 'string', 'size:2'],

            'metadata.login_count' => ['integer', 'min:0', 'max:1000'],
            'metadata.last_login_at' => ['nullable', 'date'],
            'metadata.status' => ['required', 'in:pending,approved,rejected'],

            'attachments' => ['nullable', 'array'],
            'attachments.*.file' => ['required', 'file', 'mimes:jpg,png,pdf', 'max:5120'],
            'attachments.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'credentials.email.required' => 'Email is required.',
            'credentials.password.required_if' => 'Password is required when auth type is password.',
            'credentials.otp.required_if' => 'OTP is required when auth type is otp.',
            'profile.address.postal_code.regex' => 'Postal code must be exactly 5 digits.',
            'profile.preferences.accepted_terms.accepted' => 'You must accept the terms.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
