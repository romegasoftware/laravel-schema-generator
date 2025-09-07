<?php

namespace RomegaSoftware\LaravelZodGenerator\Extractors;

use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Support\PackageDetector;

class ExtractorManager
{
    protected array $extractors = [];

    protected PackageDetector $packageDetector;

    public function __construct(PackageDetector $packageDetector)
    {
        $this->packageDetector = $packageDetector;
        $this->registerDefaultExtractors();
        $this->registerCustomExtractors();
    }

    /**
     * Register the default extractors based on available packages
     */
    protected function registerDefaultExtractors(): void
    {
        // Always register RequestClassExtractor for FormRequest support
        $this->register(new RequestClassExtractor);

        // Conditionally register DataClassExtractor if Spatie Data is available
        if ($this->packageDetector->isFeatureEnabled('data_classes')) {
            $this->register(new DataClassExtractor);
        }
    }

    /**
     * Register custom extractors from configuration
     */
    protected function registerCustomExtractors(): void
    {
        $customExtractors = config('laravel-zod-generator.custom_extractors', []);

        foreach ($customExtractors as $extractorClass) {
            try {
                $extractor = app($extractorClass);
            } catch (\Exception) {
                // If it can't be resolved from the container, check if it's a real class
                if (! class_exists($extractorClass)) {
                    throw new \InvalidArgumentException("Custom extractor class {$extractorClass} does not exist.");
                }

                // If it exists but can't be instantiated, try direct instantiation
                $extractor = new $extractorClass;
            }

            if (! $extractor instanceof ExtractorInterface) {
                throw new \InvalidArgumentException("Custom extractor {$extractorClass} must implement ExtractorInterface.");
            }

            $this->register($extractor);
        }
    }

    /**
     * Register an extractor
     */
    public function register(ExtractorInterface $extractor): void
    {
        $this->extractors[] = $extractor;

        // Sort by priority (higher first)
        usort($this->extractors, function ($a, $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * Find an appropriate extractor for the given class
     */
    public function findExtractor(ReflectionClass $class): ?ExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->canHandle($class)) {
                return $extractor;
            }
        }

        return null;
    }

    /**
     * Extract schema information from a class
     *
     * @throws \RuntimeException if no suitable extractor is found
     */
    public function extract(ReflectionClass $class): ExtractedSchemaData
    {
        $extractor = $this->findExtractor($class);

        if (! $extractor) {
            throw new \RuntimeException(
                "No extractor found for class {$class->getName()}. ".
                'Make sure the class extends FormRequest, Data, or has a rules() method.'
            );
        }

        return $extractor->extract($class);
    }

    /**
     * Get all registered extractors
     */
    public function getExtractors(): array
    {
        return $this->extractors;
    }
}
