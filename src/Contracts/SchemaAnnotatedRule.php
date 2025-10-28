<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Contracts;

use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;

/**
 * Marks a validation rule as providing a custom schema fragment override.
 */
interface SchemaAnnotatedRule
{
    public function schemaFragment(): SchemaFragment;
}
