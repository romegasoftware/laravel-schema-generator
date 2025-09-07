<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

use Spatie\LaravelData\Data;

abstract class BaseValidationRules extends Data implements ValidationRulesInterface
{
    public function __construct(
        public readonly bool $required = false,
        public readonly bool $nullable = false,
        /** @var array<string, string> */
        public readonly array $customMessages = [],
    ) {}

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function getCustomMessages(): array
    {
        return $this->customMessages;
    }

    public function getCustomMessage(string $key): ?string
    {
        return $this->customMessages[$key] ?? null;
    }

    public function hasValidation(string $key): bool
    {
        return match ($key) {
            'required' => $this->required,
            'nullable' => $this->nullable,
            default => property_exists($this, $key) && $this->$key !== null && $this->$key !== false && $this->$key !== [],
        };
    }

    public function getValidation(string $key): mixed
    {
        return match ($key) {
            'required' => $this->required,
            'nullable' => $this->nullable,
            'customMessages' => $this->customMessages,
            default => property_exists($this, $key) ? $this->$key : null,
        };
    }
}
