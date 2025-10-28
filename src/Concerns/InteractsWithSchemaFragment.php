<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Concerns;

use Closure;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;

trait InteractsWithSchemaFragment
{
    protected ?SchemaFragment $schemaFragment = null;

    protected ?string $schemaFailureMessage = null;

    public function withSchemaFragment(SchemaFragment $fragment): static
    {
        $this->schemaFragment = $fragment;

        return $this;
    }

    public function literal(string $literal): static
    {
        return $this->withSchemaFragment(SchemaFragment::literal($literal));
    }

    public function withFailureMessage(string $message): static
    {
        $this->schemaFailureMessage = $message;

        return $this;
    }

    public function failureMessage(): ?string
    {
        return $this->schemaFailureMessage;
    }

    public function append(string|callable $literal, ?string $message = null): static
    {
        $formatted = $this->formatLiteral($literal, SchemaFragment::MODE_APPEND, $message);

        return $this->withSchemaFragment(SchemaFragment::literal($formatted));
    }

    public function replace(string|callable $literal, ?string $message = null): static
    {
        $formatted = $this->formatLiteral($literal, SchemaFragment::MODE_REPLACE, $message);

        return $this->withSchemaFragment(SchemaFragment::literal($formatted));
    }

    public function schemaFragment(): SchemaFragment
    {
        if ($this->schemaFragment === null) {
            throw new \LogicException('Schema fragment has not been configured for '.static::class.'.');
        }

        return $this->schemaFragment;
    }

    public function hasSchemaFragment(): bool
    {
        return $this->schemaFragment !== null;
    }

    protected function formatLiteral(string|callable $literal, string $mode, ?string $message): string
    {
        if ($message !== null) {
            $this->schemaFailureMessage = $message;
        } elseif ($this->schemaFailureMessage !== null) {
            $message = $this->schemaFailureMessage;
        }

        $encodedMessage = $message === null ? 'null' : json_encode($message);
        if ($encodedMessage === false) {
            $encodedMessage = 'null';
        }

        if (is_callable($literal)) {
            $callable = Closure::fromCallable($literal);
            $reflection = new \ReflectionFunction($callable);
            $args = $reflection->getNumberOfParameters() >= 2
                ? [$encodedMessage, $message]
                : [$encodedMessage];
            $literalString = $callable(...$args);
        } else {
            $literalString = $literal;
        }

        if (! is_string($literalString)) {
            throw new \RuntimeException('Schema literal resolver must return a string.');
        }

        $literalString = trim($literalString);
        $literalString = preg_replace('/\s+/', ' ', $literalString);

        if ($mode === SchemaFragment::MODE_APPEND) {
            $trimmed = ltrim($literalString);
            if ($trimmed === '' || ! str_starts_with($trimmed, '.')) {
                $literalString = '.'.$literalString;
            }
        }

        return $literalString;
    }
}
