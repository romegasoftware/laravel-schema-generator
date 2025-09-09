<?php

namespace RomegaSoftware\LaravelSchemaGenerator\ZodBuilders;

class ZodEnumBuilder extends ZodBuilder
{
    protected array $values = [];

    protected ?string $enumReference = null;

    protected ?string $customMessage = null;

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
            $messageStr = $this->customMessage ? ', { message: "'.addslashes($this->customMessage).'" }' : '';

            return "z.enum({$this->enumReference}{$messageStr})";
        }

        $formattedValues = array_map(fn ($v) => '"'.$v.'"', $this->values);
        $valuesStr = implode(', ', $formattedValues);
        $messageStr = $this->customMessage ? ', { message: "'.addslashes($this->customMessage).'" }' : '';

        return "z.enum([{$valuesStr}]{$messageStr})";
    }

    /**
     * Set enum values
     */
    public function values(array $values): self
    {
        $this->values = $values;

        return $this;
    }

    /**
     * Set enum reference (e.g., 'App.StatusEnum')
     */
    public function enumReference(string $reference): self
    {
        $this->enumReference = $reference;

        return $this;
    }

    /**
     * Set custom error message for enum validation
     */
    public function message(string $message): self
    {
        $this->customMessage = $message;

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
