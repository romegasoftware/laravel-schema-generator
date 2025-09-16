<?php

namespace RomegaSoftware\LaravelSchemaGenerator;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RomegaSoftware\LaravelSchemaGenerator\Commands\GenerateValidationSchemasCommand;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Factories\FieldMetadataFactory;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Services\DataClassRuleProcessor;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Services\MessageResolutionService;
use RomegaSoftware\LaravelSchemaGenerator\Services\NestedMessageHandler;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\EnumTypeHandler;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

class LaravelSchemaGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Provide default Spatie Data config if not already configured
        if (! config()->has('data')) {
            $this->mergeConfigFrom(__DIR__.'/../config/data-defaults.php', 'data');
        }

        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-schema-generator.php',
            'laravel-schema-generator'
        );

        $this->app->singleton(PackageDetector::class);
        $this->app->singleton(ZodBuilderFactory::class);
        $this->app->singleton(LaravelValidationResolver::class);
        $this->app->singleton(MessageResolutionService::class);
        $this->app->singleton(FieldMetadataFactory::class);
        $this->app->singleton(DataClassRuleProcessor::class);
        $this->app->singleton(NestedMessageHandler::class);
        $this->app->singleton(EnumTypeHandler::class);

        // Register Spatie Data dependencies if available
        if (class_exists(\Spatie\LaravelData\Resolvers\DataValidatorResolver::class)) {
            $this->app->singleton(\Spatie\LaravelData\Resolvers\DataValidatorResolver::class);
        }

        // Register type handlers with their factory dependencies
        $this->app->bind(\RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\BaseTypeHandler::class, function ($app) {
            return new class($app->make(ZodBuilderFactory::class)) extends \RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\BaseTypeHandler
            {
                public function canHandle(string $type): bool
                {
                    return false;
                }

                public function canHandleProperty(\RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData $property): bool
                {
                    return false;
                }

                public function handle(\RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData $property): \RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface
                {
                    throw new \Exception('Base handler should not be called directly');
                }

                public function getPriority(): int
                {
                    return 0;
                }
            };
        });

        $this->app->bind(UniversalTypeHandler::class, function ($app) {
            $factory = $app->make(ZodBuilderFactory::class);
            $handler = new UniversalTypeHandler($factory);

            // Set up circular dependency: factory needs the universal handler for complex builders
            $factory->setUniversalTypeHandler($handler);

            return $handler;
        });

        // Register extractors with their dependencies
        $this->app->bind(\RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor::class, function ($app) {
            return new \RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor(
                $app->make(LaravelValidationResolver::class)
            );
        });

        if (class_exists(\Spatie\LaravelData\Resolvers\DataValidatorResolver::class)) {
            $this->app->bind(DataClassExtractor::class, function ($app) {
                return new DataClassExtractor(
                    $app->make(LaravelValidationResolver::class),
                    $app->make(\Spatie\LaravelData\Resolvers\DataValidatorResolver::class),
                    $app->make(MessageResolutionService::class),
                    $app->make(FieldMetadataFactory::class),
                    $app->make(DataClassRuleProcessor::class)
                );
            });
        }

        // Register writers with their dependencies
        $this->app->bind(\RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter::class, function ($app) {
            return new \RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter(
                $app->make(ValidationSchemaGenerator::class)
            );
        });

        // Register type handler registry as singleton
        $this->app->singleton(TypeHandlerRegistry::class, function ($app) {
            $registry = new TypeHandlerRegistry;

            // First, register the default built-in handlers using proper injection
            $registry->registerMany([
                $app->make(EnumTypeHandler::class),
                $app->make(UniversalTypeHandler::class),
            ]);

            // Then register custom handlers from config
            $customHandlers = config('laravel-schema-generator.custom_type_handlers', []);
            foreach ($customHandlers as $handlerClass) {
                if (class_exists($handlerClass)) {
                    $registry->register($app->make($handlerClass));
                }
            }

            return $registry;
        });

        // Register ValidationSchemaGenerator as singleton with custom registry
        $this->app->singleton(ValidationSchemaGenerator::class, function ($app) {
            $registry = $app->make(TypeHandlerRegistry::class);

            return new ValidationSchemaGenerator($registry);
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateValidationSchemasCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__.'/../config/laravel-schema-generator.php' => config_path('laravel-schema-generator.php'),
            ], 'laravel-schema-generator-config');
        }

        // Set up automatic hook into typescript:transform if enabled
        $this->setupTypeScriptTransformerHook();
    }

    /**
     * Set up automatic hook into typescript:transform command
     */
    protected function setupTypeScriptTransformerHook(): void
    {
        $packageDetector = $this->app->make(PackageDetector::class);

        // Check if feature is enabled
        if (! $packageDetector->isFeatureEnabled('typescript_transformer_hook')) {
            return;
        }

        // Check if the TypeScript Transformer package is available
        if (! $packageDetector->hasLaravelTypeScriptTransformer()) {
            return;
        }

        // Listen for when the typescript:transform command finishes
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            // Check if it's the typescript:transform command
            if ($event->command === 'typescript:transform' && $event->exitCode === 0) {
                $output = $event->output;

                // Check if auto-generation is enabled in config
                $output->writeln('');
                $output->writeln('<info>Running schema:generate...</info>');

                // Run the Zod schema generator
                $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->call('schema:generate', [
                    '--force' => true,
                ], $output);
            }
        });
    }
}
