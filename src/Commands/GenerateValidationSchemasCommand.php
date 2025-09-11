<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaTypeScriptWriter;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\ExtractorManager;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter;

class GenerateValidationSchemasCommand extends Command
{
    protected $signature = 'schema:generate
                            {--force : Force the operation to run when in production}
                            {--path= : Override the output path}';

    protected $description = 'Generate validation schemas from classes with #[ValidationSchema] attribute';

    public function __construct(
        protected ?PackageDetector $packageDetector = null,
        protected ?ExtractorManager $extractorManager = null,
        protected ?TypeHandlerRegistry $typeHandlerRegistry = null,
        protected ?ValidationSchemaGenerator $generator = null,
        protected ?SchemaTypeScriptWriter $writer = null
    ) {
        parent::__construct();

        $this->packageDetector = $packageDetector ?? new PackageDetector();
        $this->extractorManager = $extractorManager ?? new ExtractorManager($this->packageDetector);
        $this->typeHandlerRegistry = $typeHandlerRegistry ?? new TypeHandlerRegistry();
        $this->generator = $generator ?? new ValidationSchemaGenerator($this->typeHandlerRegistry);
        $this->writer = $writer ?? config('laravel-schema-generator.writer', ZodTypeScriptWriter::class)::make();
    }

    /**
     * Execute the console command
     */
    public function handle(): int
    {
        // Check if running in production without force flag
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Running in production! Use --force to continue.');

            return 1;
        }

        $this->info('🔍 Scanning for classes with #[ValidationSchema] attribute...');

        // Collect all classes with ValidationSchema attribute
        $classes = $this->collectClasses();

        if (empty($classes)) {
            $this->warn('No classes found with #[ValidationSchema] attribute.');
            $this->info('Add the #[ValidationSchema] attribute to your Data classes, FormRequest classes, or any class with validation rules.');

            return 0;
        }

        $this->info(sprintf('Found %d classes to process', count($classes)));

        // Process each class
        $schemas = [];
        $errors = [];

        foreach ($classes as $className) {
            try {
                $this->line("  Processing: {$className}");

                $reflectionClass = new ReflectionClass($className);
                $extractedData = $this->extractorManager->extract($reflectionClass);

                $schemas[] = $extractedData;

                $this->line("  ✓ Generated: {$extractedData->name}");
            } catch (\Exception $e) {
                $errors[] = "Failed to process {$className}: ".$e->getMessage();
                $this->error("  ✗ Failed: {$className}");
                $this->line('    '.$e->getMessage());
            }
        }

        if (empty($schemas)) {
            $this->error('No schemas were generated successfully.');

            return 1;
        }

        // Write schemas to file
        $this->info('📝 Writing schemas to file...');
        $this->writer->write($schemas);

        $this->info(sprintf('✅ Generated %d Zod schemas in %s', count($schemas), $this->writer->getOutputPath()));

        // Show any errors that occurred
        if (! empty($errors)) {
            $this->warn('The following errors occurred:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
        }

        // Show available features
        $this->showAvailableFeatures();

        return 0;
    }

    /**
     * Collect all classes with ValidationSchema attribute
     */
    protected function collectClasses(): array
    {
        $classes = [];
        $scanPaths = config('laravel-schema-generator.scan_paths', [app_path()]);

        foreach ($scanPaths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $className = $this->getClassNameFromFile($file);

                    if ($className && class_exists($className)) {
                        try {
                            $reflection = new ReflectionClass($className);

                            // Check if class has ValidationSchema attribute
                            if (! empty($reflection->getAttributes(ValidationSchema::class))) {
                                $classes[] = $className;
                            }
                        } catch (\Exception $e) {
                            // Skip classes that can't be reflected
                            continue;
                        }
                    }
                }
            }
        }

        return array_unique($classes);
    }

    /**
     * Get class name from file
     */
    protected function getClassNameFromFile($file): ?string
    {
        $content = file_get_contents($file->getPathname());

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
        } else {
            return null;
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            $className = $classMatch[1];

            return $namespace.'\\'.$className;
        }

        return null;
    }

    /**
     * Show available features based on installed packages
     */
    protected function showAvailableFeatures(): void
    {
        $this->line('');
        $this->info('📦 Available Features:');

        $features = $this->packageDetector->getAvailableFeatures();

        if ($features['data_classes']) {
            $this->line('  ✓ Spatie Data class support enabled');
        } else {
            $this->line('  ○ Spatie Data class support (install spatie/laravel-data to enable)');
        }

        if ($features['typescript_transformer']) {
            $this->line('  ✓ TypeScript Transformer integration available');
        } else {
            $this->line('  ○ TypeScript Transformer (install spatie/typescript-transformer to enable)');
        }

        if ($features['laravel_typescript_transformer']) {
            $this->line('  ✓ Automatic hook into typescript:transform command available');
        } else {
            $this->line('  ○ Auto-hook support (install spatie/laravel-typescript-transformer to enable)');
        }

        $this->line('  ✓ Laravel FormRequest support enabled');
    }
}
