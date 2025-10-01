<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema(name: 'AuthTypeSchema')]
class AuthTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:255'],
            'auth_type' => ['required', 'in:password,private_key'],
            'password' => ['required_if:auth_type,password', 'nullable', 'string'],
            'private_key' => ['required_if:auth_type,private_key', 'nullable', 'string'],
            'passphrase' => ['nullable', 'string'],
            'base_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
