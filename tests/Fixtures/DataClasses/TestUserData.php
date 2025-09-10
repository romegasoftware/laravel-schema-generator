<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

#[ValidationSchema(name: 'UserDataSchema')]
class TestUserData extends Data
{
    public function __construct(
        #[Required, Email, Max(255)]
        public string $email,

        #[Required, Min(2), Max(100)]
        public string $name,

        #[Min(18), Max(120)]
        public ?int $age = null,

        public bool $active = true
    ) {}
}
