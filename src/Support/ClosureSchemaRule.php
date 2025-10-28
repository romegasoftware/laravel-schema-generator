<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Support;

use Closure;
use Illuminate\Contracts\Validation\InvokableRule;
use Illuminate\Contracts\Validation\ValidationRule;
use RomegaSoftware\LaravelSchemaGenerator\Concerns\InteractsWithSchemaFragment;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaAnnotatedRule;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;

class ClosureSchemaRule implements InvokableRule, SchemaAnnotatedRule, ValidationRule
{
    use InteractsWithSchemaFragment;

    private Closure $callback;

    private ?string $message = null;

    private bool $supportsMessage;

    private ?Closure $literalFactory = null;

    private string $literalMode = SchemaFragment::MODE_REPLACE;

    private bool $provideEncoded = false;

    /**
     * @param  callable  $callback  Validation callback that mimics Laravel's rule closure signature.
     */
    public function __construct(callable $callback)
    {
        $this->callback = Closure::fromCallable($callback);
        $reflection = new \ReflectionFunction($this->callback);
        $this->supportsMessage = $reflection->getNumberOfParameters() >= 4;
    }

    public function replace(string|callable $literal): self
    {
        $this->literalMode = SchemaFragment::MODE_REPLACE;

        return $this->setLiteralFactory($literal, provideEncoded: true);
    }

    public function append(string|callable $literal): self
    {
        $this->literalMode = SchemaFragment::MODE_APPEND;

        return $this->setLiteralFactory($literal, provideEncoded: true);
    }

    public function failWith(string $message): self
    {
        $this->message = $message;

        if (! $this->supportsMessage) {
            $original = $this->callback;
            $this->callback = function ($attribute, $value, $fail) use ($original): void {
                $message = $this->message;
                $original($attribute, $value, function ($provided = null) use ($fail, $message): void {
                    $fail($provided ?? $message);
                });
            };
        }

        $this->refreshSchemaFragment();

        return $this;
    }

    public function __invoke($attribute, $value, $fail): void
    {
        $arguments = [$attribute, $value, $fail];

        if ($this->message !== null && $this->supportsMessage) {
            $arguments[] = $this->message;
        }

        ($this->callback)(...$arguments);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->__invoke($attribute, $value, $fail);
    }

    protected function setLiteralFactory(string|callable $literal, bool $provideEncoded): self
    {
        $this->provideEncoded = $provideEncoded;

        if (is_callable($literal)) {
            $this->literalFactory = Closure::fromCallable($literal);
        } else {
            $literalString = $literal;
            $this->literalFactory = static fn (?string $encoded, ?string $message = null): string => $literalString;
        }

        $this->refreshSchemaFragment();

        return $this;
    }

    protected function refreshSchemaFragment(): void
    {
        if ($this->literalFactory === null) {
            return;
        }

        $rawMessage = $this->message;
        $encodedMessage = $rawMessage === null ? 'null' : json_encode($rawMessage);
        if ($encodedMessage === false) {
            $encodedMessage = 'null';
        }

        $factoryReflection = new \ReflectionFunction($this->literalFactory);
        $argumentCount = $factoryReflection->getNumberOfParameters();

        if ($this->provideEncoded) {
            $args = $argumentCount >= 2
                ? [$encodedMessage, $rawMessage]
                : [$encodedMessage];
        } else {
            $args = [$rawMessage];
        }

        $literal = ($this->literalFactory)(...$args);

        if (! is_string($literal)) {
            throw new \RuntimeException('Schema literal resolver must return a string.');
        }

        $literal = trim($literal);
        $literal = preg_replace('/\s+/', ' ', $literal);

        if ($this->literalMode === SchemaFragment::MODE_APPEND) {
            $trimmed = ltrim($literal);
            if ($trimmed === '' || ! str_starts_with($trimmed, '.')) {
                $literal = '.'.$literal;
            }
        }

        $this->withSchemaFragment(SchemaFragment::literal($literal));
    }
}
