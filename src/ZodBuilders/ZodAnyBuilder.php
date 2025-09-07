<?php

namespace RomegaSoftware\LaravelZodGenerator\ZodBuilders;

class ZodAnyBuilder extends ZodBuilder
{
    protected function getBaseType(): string
    {
        return 'z.any()';
    }

    /**
     * Override build method since z.any() doesn't support additional validation chains
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
