<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | The paths where the package will look for classes with the #[ValidationSchema]
    | attribute. By default, it scans the app directory.
    |
    */
    'scan_paths' => [
        app_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Writer
    |--------------------------------------------------------------------------
    |
    | Configure which validator writer you're using.
    | Available options: ZodTypeScriptWriter
    |
    */
    'writer' => \RomegaSoftware\LaravelSchemaGenerator\Writers\ZodTypeScriptWriter::class,

    /*
    |--------------------------------------------------------------------------
    | Zod Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Zod writer behavior.
    |
    */
    'zod' => [
        // Configure where and how the generated TypeScript file will be written.
        'output' => [
            'path' => resource_path('js/types/schemas.ts'),

            // Output format: 'module' or 'namespace'
            'format' => 'module',

            // If using namespace format, specify the namespace name.
            'namespace' => 'Schemas',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | App Types Import Path
    |--------------------------------------------------------------------------
    |
    | The import path for App types when generating (or manually defining)
    | schemas for your app classes.
    | Generates `import { app_prefix } from 'app_types_import_path';`
    | Default settings will generate:
    | import { App } from '.';
    |
    | Default: '.'
    */
    'app_types_import_path' => '.',

    /*
    |--------------------------------------------------------------------------
    | App Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix of your App created classes exported by your types/index.ts.
    | Generates `import { app_prefix } from 'app_types_import_path';`
    | Default settings will generate:
    | import { App } from '.';
    |
    | Also generates app_prefix.EnumName if you have a field relying on
    | structured data within your rules.
    |
    | Default: 'App'
    */
    'app_prefix' => 'App',

    /*
    |--------------------------------------------------------------------------
    | Use App Types
    |--------------------------------------------------------------------------
    |
    | Whether to use {app_prefix}.* types in the generated schemas. This is useful
    | when you have TypeScript types generated from your PHP classes.
    |
    */
    'use_app_types' => false,

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features are enabled. Set to 'auto' to auto-detect based
    | on installed packages, or explicitly set to true/false.
    |
    */
    'features' => [
        // Support for Spatie Laravel Data classes
        'data_classes' => 'auto',

        // Hook into typescript:transform command
        'typescript_transformer_hook' => 'auto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Extractors
    |--------------------------------------------------------------------------
    |
    | Register custom extractors to handle additional validation sources.
    | Each extractor must implement ExtractorInterface.
    |
    */
    'custom_extractors' => [
        // \App\ZodExtractors\CustomExtractor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Type Handlers
    |--------------------------------------------------------------------------
    |
    | Register custom type handlers to override default behavior or add support
    | for additional types. Each handler must implement TypeHandlerInterface.
    | Handlers are processed in priority order (higher numbers = higher priority).
    |
    */
    'custom_type_handlers' => [
        // \App\ZodTypeHandlers\CustomStringHandler::class,
        // \App\ZodTypeHandlers\DateTimeHandler::class,
    ],
];
