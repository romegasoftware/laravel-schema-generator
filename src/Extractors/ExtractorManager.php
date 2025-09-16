<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Extractors;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\ExtractorInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;

class ExtractorManager
{
    protected array $extractors = [];

    protected bool $initialized = false;

    public function __construct(protected PackageDetector $packageDetector) {}

    /**
     * Initialize extractors if not already done
     */
    protected function ensureInitialized(): void
    {
        if (! $this->initialized) {
            $this->registerDefaultExtractors();
            $this->registerCustomExtractors();
            $this->initialized = true;
        }
    }

    /**
     * Register the default extractors based on available packages
     */
    protected function registerDefaultExtractors(): void
    {
        // Always register RequestClassExtractor for FormRequest support
        $this->register(app(RequestClassExtractor::class));

        // Conditionally register DataClassExtractor if Spatie Data is available
        if ($this->packageDetector->isFeatureEnabled('data_classes')) {
            $this->register(app(DataClassExtractor::class));
        }
    }

    /**
     * Register custom extractors from configuration
     */
    protected function registerCustomExtractors(): void
    {
        $customExtractors = config('laravel-schema-generator.custom_extractors', []);

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
        $this->ensureInitialized();
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
        $this->ensureInitialized();
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
        $this->ensureInitialized();

        return $this->extractors;
    }
}
