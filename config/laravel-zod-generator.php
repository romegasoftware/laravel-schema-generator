<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scan Paths
    |--------------------------------------------------------------------------
    |
    | The paths where the package will look for classes with the #[ZodSchema]
    | attribute. By default, it scans the app directory.
    |
    */
    'scan_paths' => [
        app_path(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where and how the generated TypeScript file will be written.
    |
    */
    'output' => [
        'path' => resource_path('js/types/zod-schemas.ts'),

        // Output format: 'module' or 'namespace'
        'format' => 'module',
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespace Configuration
    |--------------------------------------------------------------------------
    |
    | If using namespace format, specify the namespace name.
    |
    */
    'namespace' => 'Schemas',

    /*
    |--------------------------------------------------------------------------
    | App Types Import Path
    |--------------------------------------------------------------------------
    |
    | The import path for App types when generating schemas for Data classes.
    | This is used when the package detects Spatie Data classes.
    |
    */
    'app_types_import_path' => '.',

    /*
    |--------------------------------------------------------------------------
    | Use App Types
    |--------------------------------------------------------------------------
    |
    | Whether to use App.* types in the generated schemas. This is useful
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
