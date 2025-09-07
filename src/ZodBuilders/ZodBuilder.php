<?php

namespace RomegaSoftware\LaravelZodGenerator\ZodBuilders;

abstract class ZodBuilder
{
    protected array $chain = [];

    protected bool $nullable = false;

    protected bool $optional = false;

    /**
     * Make the field nullable
     */
    public function nullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * Make the field optional
     */
    public function optional(): self
    {
        $this->optional = true;

        return $this;
    }

    /**
     * Build the final Zod chain string
     */
    public function build(): string
    {
        $zodString = $this->getBaseType();

        // Add all validation rules
        foreach ($this->chain as $rule) {
            $zodString .= $rule;
        }

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

    /**
     * Get the base Zod type (e.g., 'z.string()', 'z.number()')
     */
    abstract protected function getBaseType(): string;

    /**
     * Add a validation rule to the chain
     */
    protected function addRule(string $rule): self
    {
        $this->chain[] = $rule;

        return $this;
    }

    /**
     * Replace an existing rule of the same type (e.g., replace .min() with new .min())
     */
    protected function replaceRule(string $ruleType, string $newRule): self
    {
        // Remove existing rule of the same type
        $this->chain = array_filter($this->chain, function ($rule) use ($ruleType) {
            return ! str_contains($rule, $ruleType.'(');
        });

        // Add the new rule
        $this->chain[] = $newRule;

        return $this;
    }

    /**
     * Check if a rule type already exists in the chain
     */
    protected function hasRule(string $ruleType): bool
    {
        foreach ($this->chain as $rule) {
            if (str_contains($rule, $ruleType.'(')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format a message for use in validation rules
     */
    protected function formatMessage(?string $message): string
    {
        if ($message === null) {
            return '';
        }

        // Escape single quotes and return formatted message
        $escapedMessage = str_replace("'", "\\'", $message);

        return ", '{$escapedMessage}'";
    }
}
