<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

class ZodObjectBuilder extends ZodBuilder
{
    protected string $schemaReference;

    public function __construct(string $schemaReference)
    {
        $this->schemaReference = $schemaReference;
    }

    protected function getBaseType(): string
    {
        return $this->schemaReference;
    }

    /**
     * Set the schema reference
     */
    public function validateSchemaReference(?array $parameters = [], ?string $message = null): self
    {
        [$reference] = $parameters;
        $this->schemaReference = $reference;

        return $this;
    }

    /**
     * Get the schema reference
     */
    public function getSchemaReference(): string
    {
        return $this->schemaReference;
    }

    /**
     * Override build method since object references don't support additional validation chains
     * except for nullable and optional
     */
    public function build(): string
    {
        $zodString = $this->schemaReference;

        // Add nullable if specified
        if ($this->nullable) {
            $zodString .= '.nullable()';
        }

        // Add optional if specified
        if ($this->optional) {
            $zodString .= '.optional()';
        }

        return $zodString;
    }
}
