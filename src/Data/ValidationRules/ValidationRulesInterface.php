<?php

namespace RomegaSoftware\LaravelZodGenerator\Data\ValidationRules;

interface ValidationRulesInterface
{
    /**
     * Check if the field is required
     */
    public function isRequired(): bool;

    /**
     * Check if the field is nullable
     */
    public function isNullable(): bool;

    /**
     * Get all custom messages
     */
    public function getCustomMessages(): array;

    /**
     * Check if a specific validation exists
     */
    public function hasValidation(string $key): bool;

    /**
     * Get a specific validation value
     */
    public function getValidation(string $key): mixed;

    /**
     * Get a custom message for a specific validation rule
     */
    public function getCustomMessage(string $key): ?string;
}
