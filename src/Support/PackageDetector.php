<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Support;

class PackageDetector
{
    /**
     * Check if Spatie Laravel Data package is installed
     */
    public function hasSpatieData(): bool
    {
        return class_exists(\Spatie\LaravelData\Data::class);
    }

    /**
     * Check if Spatie TypeScript Transformer package is installed
     */
    public function hasTypeScriptTransformer(): bool
    {
        /** @disregard **/
        // @phpstan-ignore-next-line
        return class_exists(\Spatie\TypeScriptTransformer\TypeScriptTransformer::class);
    }

    /**
     * Check if Spatie Laravel TypeScript Transformer package is installed
     */
    public function hasLaravelTypeScriptTransformer(): bool
    {
        /** @disregard **/
        // @phpstan-ignore-next-line
        return class_exists(\Spatie\LaravelTypeScriptTransformer\Commands\TypeScriptTransformCommand::class); // @phpstan-ignore-line
    }

    /**
     * Get all available features based on installed packages
     */
    public function getAvailableFeatures(): array
    {
        return [
            'data_classes' => $this->hasSpatieData(),
            'typescript_transformer' => $this->hasTypeScriptTransformer(),
            'laravel_typescript_transformer' => $this->hasLaravelTypeScriptTransformer(),
        ];
    }

    /**
     * Check if a specific feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        $configValue = config("laravel-schema-generator.features.{$feature}", 'auto');

        if ($configValue === 'auto') {
            return match ($feature) {
                'data_classes' => $this->hasSpatieData(),
                'typescript_transformer_hook' => $this->hasLaravelTypeScriptTransformer(),
                default => false,
            };
        }

        return (bool) $configValue;
    }
}
