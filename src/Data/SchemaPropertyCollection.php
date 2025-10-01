<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, SchemaPropertyData>
 */
class SchemaPropertyCollection extends Collection
{
    /**
     * Backwards compatible accessor mirroring Spatie's DataCollection::toCollection().
     */
    public function toCollection(): Collection
    {
        return new Collection($this->items);
    }
}
