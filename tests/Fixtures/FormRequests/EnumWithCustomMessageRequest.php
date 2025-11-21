<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\Enums\TestStatusEnum;

#[ValidationSchema(name: 'EnumWithCustomMessageRequestSchema')]
class EnumWithCustomMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TestStatusEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.enum' => 'Please choose a valid state from the available options.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
