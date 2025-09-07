<?php

namespace RomegaSoftware\LaravelZodGenerator;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RomegaSoftware\LaravelZodGenerator\Commands\GenerateZodSchemasCommand;
use RomegaSoftware\LaravelZodGenerator\Generators\ZodSchemaGenerator;
use RomegaSoftware\LaravelZodGenerator\Support\PackageDetector;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerRegistry;

class LaravelZodGeneratorServiceProvider extends ServiceProvider
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
            __DIR__.'/../config/laravel-zod-generator.php',
            'laravel-zod-generator'
        );

        // Register package detector as singleton
        $this->app->singleton(PackageDetector::class);

        // Register type handler registry as singleton
        $this->app->singleton(TypeHandlerRegistry::class, function ($app) {
            $registry = new TypeHandlerRegistry;

            // First, register the default built-in handlers
            $registry->registerMany([
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\InRuleTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\EnumTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\EmailTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\DataClassTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\StringTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\NumberTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\BooleanTypeHandler,
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\ArrayTypeHandler($registry),
                new \RomegaSoftware\LaravelZodGenerator\TypeHandlers\FallbackTypeHandler,
            ]);

            // Then register custom handlers from config
            $customHandlers = config('laravel-zod-generator.custom_type_handlers', []);
            foreach ($customHandlers as $handlerClass) {
                if (class_exists($handlerClass)) {
                    $registry->register($app->make($handlerClass));
                }
            }

            return $registry;
        });

        // Register ZodSchemaGenerator as singleton with custom registry
        $this->app->singleton(ZodSchemaGenerator::class, function ($app) {
            $registry = $app->make(TypeHandlerRegistry::class);

            return new ZodSchemaGenerator($registry);
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
                GenerateZodSchemasCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__.'/../config/laravel-zod-generator.php' => config_path('laravel-zod-generator.php'),
            ], 'laravel-zod-generator-config');
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
                $output->writeln('<info>Running zod:generate...</info>');

                // Run the Zod schema generator
                $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->call('zod:generate', [
                    '--force' => true,
                ], $output);
            }
        });
    }
}
