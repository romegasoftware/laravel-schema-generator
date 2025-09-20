<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Traits\Macroable;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\Traits\ValidatesSizeAttributes;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;

abstract class ZodBuilder implements BuilderInterface
{
    use Macroable;
    use ValidatesSizeAttributes;

    protected ?Translator $translator = null;

    protected array $chain = [];

    protected bool $nullable = false;

    protected bool $optional = false;

    /** Context for auto-message resolution */
    protected ?string $fieldName = null;

    public SchemaPropertyData $property;

    protected ?string $requiredMessage = null;

    /**
     * Set the translator instance
     */
    public function setTranslator(?Translator $translator): self
    {
        $this->translator = $translator;

        return $this;
    }

    /**
     * Logic to setup the builder. By default we just return $this.
     * Extending classes can override this.
     */
    public function setup(): self
    {
        return $this;
    }

    public function setProperty(SchemaPropertyData $schemaProperty): self
    {
        $this->property = $schemaProperty;

        return $this;
    }

    /**
     * Set field name for auto-message resolution
     */
    public function setFieldName(string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

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
     * Auto-resolve Laravel validation message with localization support
     */
    protected function resolveMessage(string $rule, ?string $customMessage = null, array $parameters = []): ?string
    {
        // Use custom message if provided
        if ($customMessage !== null) {
            return $customMessage;
        }

        // Skip auto-resolution if we don't have field context or translator
        if ($this->fieldName === null || $this->translator === null) {
            return null;
        }

        try {
            // Get Laravel's localized message
            $fieldDisplayName = ucfirst(str_replace(['_', '-'], ' ', $this->fieldName));

            $messagePath = "validation.{$rule}";
            $messageParams = array_merge([
                'attribute' => $fieldDisplayName,
            ], $parameters);

            // Try to get the translation directly - it will return the key if not found
            $translation = $this->translator->get($messagePath, $messageParams);

            // Check if we got an actual translation or just the key back
            if ($translation !== $messagePath) {
                return $translation;
            }
        } catch (\Throwable) {
            // Silently fall back to no message if translator context isn't available
        }

        return null;
    }

    /**
     * Format a message for use as a method parameter (e.g., .min(1, 'message'))
     * Returns the message formatted with comma and quotes: , 'message'
     */
    public function formatMessageAsParameter(?string $str): string
    {
        if ($str === null) {
            return '';
        }

        $escapedStr = $this->normalizeMessageForJS($str);

        return ", '{$escapedStr}'";
    }

    /**
     * Unified message normalization for JavaScript output
     * Handles all escaping consistently for embedding in JavaScript strings
     * Use this when you need to embed a message directly in a JS string
     */
    public function normalizeMessageForJS(?string $str): string
    {
        if ($str === null) {
            return '';
        }

        // Escape all special characters for JavaScript strings
        return str_replace(
            ['\\', "'", '"', "\n", "\r", "\t"],
            ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'],
            $str
        );
    }
}
