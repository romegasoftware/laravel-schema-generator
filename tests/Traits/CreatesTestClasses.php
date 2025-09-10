<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Traits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Data;

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

    protected function createTestDataClass(array $properties = []): Data
    {
        return new #[ValidationSchema] class($properties) extends Data
        {
            public function __construct(
                public array $properties = []
            ) {}
        };
    }

    protected function createValidator(array $data = [], array $rules = [], array $messages = []): Validator
    {
        $translator = new Translator(new ArrayLoader, 'en');
        $validator = new Validator($translator, $data, $rules);

        if (! empty($messages)) {
            $validator->setCustomMessages($messages);
        }

        return $validator;
    }

    protected function createAnonymousEnum(array $cases): object
    {
        $enumClass = new class($cases)
        {
            private array $cases;

            public function __construct(array $cases)
            {
                $this->cases = $cases;
            }

            public static function cases(): array
            {
                return $this->cases;
            }
        };

        return $enumClass;
    }
}
