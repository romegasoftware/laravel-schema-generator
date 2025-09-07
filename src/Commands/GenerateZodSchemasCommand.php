<?php

namespace RomegaSoftware\LaravelZodGenerator\Commands;

use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RomegaSoftware\LaravelZodGenerator\Attributes\ZodSchema;
use RomegaSoftware\LaravelZodGenerator\Extractors\ExtractorManager;
use RomegaSoftware\LaravelZodGenerator\Generators\TypeScriptWriter;
use RomegaSoftware\LaravelZodGenerator\Generators\ZodSchemaGenerator;
use RomegaSoftware\LaravelZodGenerator\Support\PackageDetector;

class GenerateZodSchemasCommand extends Command
{
    protected $signature = 'zod:generate
                            {--force : Force the operation to run when in production}
                            {--path= : Override the output path}';

    protected $description = 'Generate Zod validation schemas from classes with #[ZodSchema] attribute';

    protected ExtractorManager $extractorManager;

    protected ZodSchemaGenerator $generator;

    protected TypeScriptWriter $writer;

    protected PackageDetector $packageDetector;

    public function __construct(
        ?PackageDetector $packageDetector = null,
        ?ExtractorManager $extractorManager = null,
        ?ZodSchemaGenerator $generator = null,
        ?TypeScriptWriter $writer = null
    ) {
        parent::__construct();

        $this->packageDetector = $packageDetector ?? new PackageDetector;
        $this->extractorManager = $extractorManager ?? new ExtractorManager($this->packageDetector);
        $this->generator = $generator ?? new ZodSchemaGenerator;
        $this->writer = $writer ?? new TypeScriptWriter($this->generator);
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

        $this->info('🔍 Scanning for classes with #[ZodSchema] attribute...');

        // Collect all classes with ZodSchema attribute
        $classes = $this->collectClasses();

        if (empty($classes)) {
            $this->warn('No classes found with #[ZodSchema] attribute.');
            $this->info('Add the #[ZodSchema] attribute to your Data classes, FormRequest classes, or any class with validation rules.');

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

        // Get output path
        $outputPath = $this->option('path') ?? config('laravel-zod-generator.output.path');

        // Write schemas to file
        $this->info('📝 Writing schemas to file...');
        $this->writer->write($schemas, $outputPath);

        $this->info(sprintf('✅ Generated %d Zod schemas in %s', count($schemas), $outputPath));

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
     * Collect all classes with ZodSchema attribute
     */
    protected function collectClasses(): array
    {
        $classes = [];
        $scanPaths = config('laravel-zod-generator.scan_paths', [app_path()]);

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

                            // Check if class has ZodSchema attribute
                            if (! empty($reflection->getAttributes(ZodSchema::class))) {
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
