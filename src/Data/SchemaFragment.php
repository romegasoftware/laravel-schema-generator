<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

/**
 * Represents a literal snippet of TypeScript schema code.
 */
class SchemaFragment
{
    public const MODE_REPLACE = 'replace';

    public const MODE_APPEND = 'append';

    public function __construct(
        private readonly string $code,
        private readonly string $mode = self::MODE_REPLACE
    ) {}

    public static function literal(string $code): self
    {
        $trimmed = ltrim($code);
        $mode = str_starts_with($trimmed, '.') ? self::MODE_APPEND : self::MODE_REPLACE;

        return new self($code, $mode);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function replaces(): bool
    {
        return $this->mode === self::MODE_REPLACE;
    }

    public function appends(): bool
    {
        return $this->mode === self::MODE_APPEND;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
