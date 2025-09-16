<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Traits;

use Illuminate\Foundation\Http\FormRequest;

trait CreatesTestClasses
{
    protected function createTestFormRequest(array $rules = [], array $messages = []): FormRequest
    {
        return new class($rules, $messages) extends FormRequest
        {
            public function __construct(
                private array $rules = [],
                private array $messages = []
            ) {
                parent::__construct();
            }

            public function rules(): array
            {
                return $this->rules;
            }

            public function messages(): array
            {
                return $this->messages;
            }
        };
    }
}
