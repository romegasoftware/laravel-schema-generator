<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

use Illuminate\Contracts\Translation\Translator;
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
     * Unified message normalization for JavaScript output
     * Handles all escaping consistently for embedding in JavaScript strings
     * Use this when you need to embed a message directly in a JS string
     */
    public function normalizeMessageForJS(?string $str): string;

    /**
     * Format a message for use as a method parameter (e.g., .min(1, 'message'))
     * Returns the message formatted with comma and quotes: , 'message'
     */
    public function formatMessageAsParameter(?string $str): string;

    /**
     * Set the translator instance
     */
    public function setTranslator(?Translator $translator): self;

    /**
     * Logic to setup the builder. (i.e.: The nesting logic for an array)
     */
    public function setup(): self;
}
