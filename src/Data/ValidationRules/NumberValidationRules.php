<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

class NumberValidationRules extends BaseValidationRules
{
    public function __construct(
        // Base properties
        bool $required = false,
        bool $nullable = false,
        array $customMessages = [],

        // Number-specific properties
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly bool $positive = false,
        public readonly bool $negative = false,
        public readonly bool $finite = false,
        public readonly ?int $gte = null,
        public readonly ?int $lte = null,
    ) {
        parent::__construct($required, $nullable, $customMessages);
    }
}
