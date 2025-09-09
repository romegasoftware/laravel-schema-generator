<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;

interface BuilderInterface
{
    public SchemaPropertyData $property {get; set; }

    /**
     * Set field name for auto-message resolution
     */
    public function setFieldName(string $fieldName): self;

    /**
     * Make the field nullable
     */
    public function nullable(): self;

    /**
     * Make the field optional
     */
    public function optional(): self;

    /**
     * Build the final Zod chain string
     */
    public function build(): string;

    /**
     * Escape string for JavaScript
     */
    public function escapeForJS(string $str): string;
}
