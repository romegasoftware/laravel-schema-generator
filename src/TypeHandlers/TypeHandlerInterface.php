<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;

interface TypeHandlerInterface
{
    /**
     * Determine if this handler can handle the given type
     */
    public function canHandle(string $type): bool;

    /**
     * Determine if this handler can handle the entire property (optional method)
     * Useful for handlers that need to check validations, not just type
     */
    public function canHandleProperty(SchemaPropertyData $property): bool;

    /**
     * Handle the property and return a ZodBuilder
     */
    public function handle(SchemaPropertyData $property): ZodBuilder;

    /**
     * Get the priority of this handler (higher numbers = higher priority)
     */
    public function getPriority(): int;
}
