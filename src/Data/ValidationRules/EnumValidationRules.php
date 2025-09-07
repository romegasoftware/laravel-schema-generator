<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

class EnumValidationRules extends BaseValidationRules
{
    public function __construct(
        // Base properties
        bool $required = false,
        bool $nullable = false,
        array $customMessages = [],

        // Enum-specific properties
        /** @var string[] */
        public readonly array $in = [],
    ) {
        parent::__construct($required, $nullable, $customMessages);
    }
}
