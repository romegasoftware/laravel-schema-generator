<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

class BooleanValidationRules extends BaseValidationRules
{
    public function __construct(
        // Base properties only - booleans have minimal validation
        bool $required = false,
        bool $nullable = false,
        array $customMessages = [],
    ) {
        parent::__construct($required, $nullable, $customMessages);
    }
}
