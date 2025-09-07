<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

class ArrayValidationRules extends BaseValidationRules
{
    public function __construct(
        // Base properties
        bool $required = false,
        bool $nullable = false,
        array $customMessages = [],

        // Array-specific properties
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $arrayItemValidations = null,
    ) {
        parent::__construct($required, $nullable, $customMessages);
    }
}
