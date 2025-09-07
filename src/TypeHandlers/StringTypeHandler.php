<?php

namespace RomegaSoftware\LaravelZodGenerator\TypeHandlers;

use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;

class StringTypeHandler implements TypeHandlerInterface
{
    public function canHandle(string $type): bool
    {
        return $type === 'string';
    }

    public function canHandleProperty(SchemaPropertyData $property): bool
    {
        return $this->canHandle($property->type);
    }

    public function handle(SchemaPropertyData $property): ZodBuilder
    {
        $builder = new ZodStringBuilder;
        $validations = $property->validations;
        $propertyName = $property->name;

        // Handle optional
        if ($property->isOptional && (! $validations || ! $validations->isRequired())) {
            $builder->optional();
        }

        if (! $validations) {
            return $builder;
        }

        // Handle required validation (adds trim and min)
        if ($validations->isRequired()) {
            $message = $validations->getCustomMessage('required')
                ?? $validations->getCustomMessage('min')
                ?? ucfirst(str_replace('_', ' ', $propertyName)).' is required';

            // Use the min value if specified, otherwise default to 1
            $minValue = $validations->getValidation('min') ?? 1;
            $builder->trim()->min($minValue, $message);
        } else {
            // Add min validation if specified without required
            if ($validations->hasValidation('min')) {
                $message = $validations->getCustomMessage('min');
                $builder->min($validations->getValidation('min'), $message);
            }
        }

        // Add max validation
        if ($validations->hasValidation('max')) {
            $message = $validations->getCustomMessage('max');
            $builder->max($validations->getValidation('max'), $message);
        }

        // Add regex validation
        if ($validations->hasValidation('regex')) {
            $pattern = $this->convertPhpRegexToJavaScript($validations->getValidation('regex'));
            $message = $validations->getCustomMessage('regex');
            $builder->regex($pattern, $message);
        }

        // Add URL validation
        if ($validations->hasValidation('url')) {
            $message = $validations->getCustomMessage('url');
            $builder->url($message);
        }

        // Add UUID validation
        if ($validations->hasValidation('uuid')) {
            $message = $validations->getCustomMessage('uuid');
            $builder->uuid($message);
        }

        // Handle nullable
        if ($validations->isNullable()) {
            $builder->nullable();
        }

        return $builder;
    }

    public function getPriority(): int
    {
        return 100;
    }

    /**
     * Convert PHP regex to JavaScript regex
     */
    protected function convertPhpRegexToJavaScript(string $phpRegex): string
    {
        // Remove the delimiters (first and last character)
        $pattern = substr($phpRegex, 1, -1);

        // In JavaScript, dots don't need escaping inside character classes
        $pattern = preg_replace_callback(
            '/\[[^\]]*\]/',
            fn ($matches) => str_replace('\.', '.', $matches[0]),
            $pattern
        );

        // Return as a JavaScript regex literal
        return '/'.$pattern.'/';
    }
}
