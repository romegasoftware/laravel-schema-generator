<?php

namespace RomegaSoftware\LaravelZodGenerator\Extractors;

use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;

interface ExtractorInterface
{
    /**
     * Check if this extractor can handle the given class
     */
    public function canHandle(ReflectionClass $class): bool;

    /**
     * Extract validation schema information from the class
     */
    public function extract(ReflectionClass $class): ExtractedSchemaData;

    /**
     * Get the priority of this extractor (higher = checked first)
     */
    public function getPriority(): int;
}
