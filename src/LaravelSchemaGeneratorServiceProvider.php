<?php

namespace RomegaSoftware\LaravelSchemaGenerator;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RomegaSoftware\LaravelSchemaGenerator\Commands\GenerateValidationSchemasCommand;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Factories\FieldMetadataFactory;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Resolvers\InheritingDataValidationMessagesAndAttributesResolver;
use RomegaSoftware\LaravelSchemaGenerator\Resolvers\InheritingDataValidationRulesResolver;
use RomegaSoftware\LaravelSchemaGenerator\Services\DataClassRuleProcessor;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use RomegaSoftware\LaravelSchemaGenerator\Services\MessageResolutionService;
use RomegaSoftware\LaravelSchemaGenerator\Services\NestedMessageHandler;
use RomegaSoftware\LaravelSchemaGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\EnumTypeHandler;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;
use RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter;
use Spatie\LaravelData\Resolvers\DataValidationMessagesAndAttributesResolver;
use Spatie\LaravelData\Resolvers\DataValidationRulesResolver;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer;
use Spatie\LaravelData\Support\Validation\RuleNormalizer;

class LaravelSchemaGeneratorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerCoreServices();
        $this->registerTypeHandlers();
        $this->registerExtractors();
        $this->registerGeneratorsAndWriters();
        $this->registerTypeHandlerRegistry();
    }

    /**
     * Register configuration files
     */
    protected function registerConfig(): void
    {
        // Provide default Spatie Data config if not already configured
        if (! config()->has('data') && $this->spatieDataAvailable()) {
            $this->mergeConfigFrom(__DIR__.'/../config/data-defaults.php', 'data');
        }

        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-schema-generator.php',
            'laravel-schema-generator'
        );
    }

    /**
     * Register core services that can be auto-resolved
     */
    protected function registerCoreServices(): void
    {
        // Simple singletons that Laravel can auto-resolve
        $this->app->singleton(PackageDetector::class);
        $this->app->singleton(LaravelValidationResolver::class);
        $this->app->singleton(MessageResolutionService::class);
        $this->app->singleton(FieldMetadataFactory::class);
        $this->app->singleton(DataClassRuleProcessor::class);
        $this->app->singleton(NestedMessageHandler::class);

        // Register Spatie Data validator resolver if available
        if ($this->spatieDataAvailable()) {
            $this->app->singleton(\Spatie\LaravelData\Resolvers\DataValidatorResolver::class);
            $this->app->singleton(DataValidationRulesResolver::class, function ($app) {
                return new InheritingDataValidationRulesResolver(
                    $app->make(DataConfig::class),
                    $app->make(RuleNormalizer::class),
                    $app->make(RuleDenormalizer::class),
                    $this->resolveDataMorphClassResolver($app),
                );
            });
            $this->app->singleton(DataValidationMessagesAndAttributesResolver::class, function ($app) {
                return new InheritingDataValidationMessagesAndAttributesResolver(
                    $app->make(DataConfig::class)
                );
            });
        }
    }

    /**
     * Register type handlers with special dependency handling
     */
    protected function registerTypeHandlers(): void
    {
        // ZodBuilderFactory with optional translator
        $this->app->singleton(ZodBuilderFactory::class, function ($app) {
            return new ZodBuilderFactory(
                $app->bound('translator') ? $app->make('translator') : null
            );
        });

        // EnumTypeHandler - simple dependency injection
        $this->app->singleton(EnumTypeHandler::class);

        // UniversalTypeHandler with circular dependency handling
        $this->app->singleton(UniversalTypeHandler::class, function ($app) {
            $factory = $app->make(ZodBuilderFactory::class);
            $handler = new UniversalTypeHandler($factory);

            // Handle circular dependency: factory needs handler for complex builders
            $factory->setUniversalTypeHandler($handler);

            return $handler;
        });
    }

    /**
     * Register extractor services
     */
    protected function registerExtractors(): void
    {
        // Request class extractor
        $this->app->bind(RequestClassExtractor::class);

        // Data class extractor (only if Spatie Data is available)
        if ($this->spatieDataAvailable()) {
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
    }

    /**
     * Register generators and writers
     */
    protected function registerGeneratorsAndWriters(): void
    {
        // Validation schema generator with registry
        $this->app->singleton(ValidationSchemaGenerator::class, function ($app) {
            return new ValidationSchemaGenerator(
                $app->make(TypeHandlerRegistry::class)
            );
        });

        // TypeScript writer
        $this->app->bind(ZodTypeScriptWriter::class);
    }

    /**
     * Register the type handler registry
     */
    protected function registerTypeHandlerRegistry(): void
    {
        $this->app->singleton(TypeHandlerRegistry::class, function ($app) {
            $registry = new TypeHandlerRegistry;

            // Register built-in handlers
            $registry->registerMany([
                $app->make(EnumTypeHandler::class),
                $app->make(UniversalTypeHandler::class),
            ]);

            // Register custom handlers from config
            $customHandlers = config('laravel-schema-generator.custom_type_handlers', []);
            foreach ($customHandlers as $handlerClass) {
                if (class_exists($handlerClass)) {
                    $registry->register($app->make($handlerClass));
                }
            }

            return $registry;
        });
    }

    /**
     * Check if Spatie Laravel Data package is available
     */
    protected function spatieDataAvailable(): bool
    {
        return class_exists(\Spatie\LaravelData\Resolvers\DataValidatorResolver::class);
    }

    /**
     * Resolve the legacy morph class resolver if the installed Spatie version requires it.
     */
    protected function resolveDataMorphClassResolver($app): ?object
    {
        if (! InheritingDataValidationRulesResolver::requiresDataMorphClassResolver()) {
            return null;
        }

        $resolverClass = 'Spatie\\LaravelData\\Resolvers\\DataMorphClassResolver';

        if (! class_exists($resolverClass)) {
            return null;
        }

        return $app->make($resolverClass);
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

    /**
     * Get the services provided by the provider
     *
     * @return array<class-string>
     */
    public function provides(): array
    {
        return [
            PackageDetector::class,
            LaravelValidationResolver::class,
            MessageResolutionService::class,
            FieldMetadataFactory::class,
            DataClassRuleProcessor::class,
            NestedMessageHandler::class,
            DataValidationRulesResolver::class,
            DataValidationMessagesAndAttributesResolver::class,
            ZodBuilderFactory::class,
            EnumTypeHandler::class,
            UniversalTypeHandler::class,
            TypeHandlerRegistry::class,
            ValidationSchemaGenerator::class,
            DataClassExtractor::class,
            GenerateValidationSchemasCommand::class,
            RequestClassExtractor::class,
            ZodTypeScriptWriter::class,
        ];
    }
}
