<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

class ZodEnumBuilder extends ZodBuilder
{
    protected ?string $requiredMessage = null;

    protected array $values = [];

    protected ?string $enumReference = null;

    public function __construct(array $values = [], ?string $enumReference = null)
    {
        $this->values = $values;
        $this->enumReference = $enumReference;
    }

    public function setEnumReference(?string $enumReference = null): self
    {
        $this->enumReference = $enumReference;

        return $this;
    }

    public function setValues(array|string $values = []): self
    {
        if (is_string($values)) {
            $enumValues = [];
            if (str_starts_with($values, 'enum:')) {
                $valueString = substr($values, 5);
                $enumValues = explode(',', $valueString);
            }
            $this->values = $enumValues;
        } else {
            $this->values = $values;
        }

        return $this;
    }

    protected function getBaseType(): string
    {
        if ($this->enumReference) {
            $messageStr = $this->requiredMessage ? ', { message: "'.addslashes($this->requiredMessage).'" }' : '';

            return "z.enum({$this->enumReference}{$messageStr})";
        }

        $formattedValues = array_map(fn ($v) => '"'.$v.'"', $this->values);
        $valuesStr = implode(', ', $formattedValues);
        $messageStr = $this->requiredMessage ? ', { message: "'.addslashes($this->requiredMessage).'" }' : '';

        return "z.enum([{$valuesStr}]{$messageStr})";
    }

    /**
     * Set enum values
     */
    public function validateValues(?array $parameters = [], ?string $message = null): self
    {
        // Parameters is already an array of values in this case
        $this->values = $parameters;

        return $this;
    }

    /**
     * Set enum reference (e.g., 'App.StatusEnum')
     */
    public function validateEnumReference(?array $parameters = [], ?string $message = null): self
    {
        [$reference] = $parameters;
        $this->enumReference = $reference;

        return $this;
    }

    public function validateRequired(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('required', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
            $this->requiredMessage = $escapedMessage;
        }

        return $this;
    }

    /**
     * Get the enum values
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get the enum reference
     */
    public function getEnumReference(): ?string
    {
        return $this->enumReference;
    }

    /**
     * Override build method since enums don't support additional chain methods
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
