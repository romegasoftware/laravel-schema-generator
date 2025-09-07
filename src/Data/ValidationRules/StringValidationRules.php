<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

class StringValidationRules extends BaseValidationRules
{
    public function __construct(
        // Base properties
        bool $required = false,
        bool $nullable = false,
        array $customMessages = [],

        // String-specific properties
        public readonly bool $string = true, // Always true for string validations
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $regex = null,
        public readonly bool $email = false,
        public readonly bool $url = false,
        public readonly bool $uuid = false,
        public readonly bool $confirmed = false,
        public readonly bool $unique = false,
        /** @var string[] */
        public readonly array $in = [],
    ) {
        parent::__construct($required, $nullable, $customMessages);
    }
}
