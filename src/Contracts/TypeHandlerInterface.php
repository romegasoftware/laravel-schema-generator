<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;

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
     * Handle the property and return a BuilderInterface
     */
    public function handle(SchemaPropertyData $property): BuilderInterface;

    /**
     * Get the priority of this handler (higher numbers = higher priority)
     */
    public function getPriority(): int;
}
