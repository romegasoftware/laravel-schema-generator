<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Writers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Traits\Macroable;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaTypeScriptWriter;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;

abstract class BaseScriptWriter implements SchemaTypeScriptWriter
{
    use Macroable;
    use Makeable;

    abstract public function getOutputPath(): string;

    /**
     * Write schemas to TypeScript file
     *
     * @param  ExtractedSchemaData[]  $schemas
     */
    public function write(array $schemas): void
    {
        $content = $this->generateContent($schemas);

        // Ensure directory exists
        $directory = dirname($this->getOutputPath());
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($this->getOutputPath(), $content);
    }

    /**
     * Get the original class name for TypeScript
     */
    public function getOriginalClassName(ExtractedSchemaData $schema): ?string
    {
        if (! isset($schema->className)) {
            return null;
        }

        $className = class_basename($schema->className);

        // If it's a Request class and we're using TypeScript transformer
        if (str_ends_with($className, 'Request') &&
            config('laravel-schema-generator.use_app_types', false)) {
            return $className;
        }

        // If it's a Data class
        if (str_ends_with($className, 'Data')) {
            return $className;
        }

        return null;
    }

    /**
     * Get the original class name for TypeScript
     */
    public function isDataClass(ExtractedSchemaData $schema): bool
    {
        if (! isset($schema->className)) {
            return false;
        }

        return new ReflectionClass($schema->className)->isSubclassOf(\Spatie\LaravelData\Data::class);
    }

    /**
     * Indent a string
     */
    public function indentString(string $string, int $spaces): string
    {
        $lines = explode("\n", $string);
        $indent = str_repeat(' ', $spaces);

        return implode("\n", array_map(function ($line) use ($indent) {
            return $line ? $indent.$line : $line;
        }, $lines));
    }
}
