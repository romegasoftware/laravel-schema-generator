<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

class ZodBooleanBuilder extends ZodBuilder
{
    protected function getBaseType(): string
    {
        return 'z.boolean()';
    }

    public function build(): string
    {
        $base = parent::build();

        return "z.preprocess((val) => {"
            .' if (typeof val === "string") {'
            .' const normalized = val.toLowerCase();'
            .' if (normalized === "true" || normalized === "1" || normalized === "on" || normalized === "yes") { return true; }'
            .' if (normalized === "false" || normalized === "0" || normalized === "off" || normalized === "no") { return false; }'
            .' }'
            .' if (val === 1) { return true; }'
            .' if (val === 0) { return false; }'
            .' return val;'
            ." }, {$base})";
    }
}
