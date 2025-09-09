<?php

namespace RomegaSoftware\LaravelSchemaGenerator\ZodBuilders;

class ZodBooleanBuilder extends ZodBuilder
{
    protected function getBaseType(): string
    {
        return 'z.boolean()';
    }

    /**
     * Override build method since boolean types don't support additional validation chains
     * except for nullable and optional
     */
    public function build(): string
    {
        $zodString = $this->getBaseType();

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
