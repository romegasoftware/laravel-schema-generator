<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\Validation;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema(name: 'InlineRuleClassSchema')]
class InlineRulesClass
{
    public function __construct(
        protected array $payload = []
    ) {}

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required_if:email,admin@example.com', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required',
            'role.required_if' => 'Role is required when email is admin@example.com',
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => 'User role',
        ];
    }
}
