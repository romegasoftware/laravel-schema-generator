<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, ResolvedValidation>
 */
class ResolvedValidationCollection extends Collection
{
    /**
     * Maintain compatibility with prior Spatie DataCollection behaviour.
     */
    public function toCollection(): Collection
    {
        return new Collection($this->items);
    }
}
