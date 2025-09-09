<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;
use Spatie\LaravelData\DataCollection;

class CustomTypeHandlerTest extends TestCase
{
    #[Test]
    public function it_allows_custom_type_handlers(): void
    {
        // Create a custom handler for datetime fields
        $customHandler = new class implements TypeHandlerInterface
        {
            public function canHandle(string $type): bool
            {
                return $type === 'datetime';
            }

            public function canHandleProperty(SchemaPropertyData $property): bool
            {
                return $property->validations instanceof ResolvedValidationSet &&
                       $property->validations->inferredType === 'datetime';
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                $builder = new ZodStringBuilder;

                // Custom datetime validation - requires ISO format
                $builder->regex('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', 'Must be valid ISO datetime');

                // Handle nullable/optional
                $validations = $property->validations;
                if ($validations->isFieldNullable()) {
                    $builder->nullable();
                }

                if ($property->isOptional && ! $validations->isFieldRequired()) {
                    $builder->optional();
                }

                return $builder;
            }

            public function getPriority(): int
            {
                return 500; // High priority to override default behavior
            }
        };

        // Create registry with custom handler
        $registry = new TypeHandlerRegistry;
        $registry->register($customHandler);

        // Create generator with custom registry
        $generator = new ValidationSchemaGenerator($registry);

        $property = new SchemaPropertyData(
            name: 'created_at',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('created_at', [], 'datetime'),
        );

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            properties: new DataCollection(SchemaPropertyData::class, [$property]),
            className: 'TestClass',
            type: 'test',
        );

        $schema = $generator->generate($extracted);

        $this->assertStringContainsString(
            "created_at: z.string().regex(/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}/, 'Must be valid ISO datetime')",
            $schema
        );
    }

    #[Test]
    public function it_allows_overriding_default_handlers(): void
    {
        // Create a custom string handler that adds different behavior
        $customStringHandler = new class implements TypeHandlerInterface
        {
            public function canHandle(string $type): bool
            {
                return $type === 'string';
            }

            public function canHandleProperty(SchemaPropertyData $property): bool
            {
                return $property->validations instanceof ResolvedValidationSet &&
                       $property->validations->inferredType === 'string';
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                $builder = new ZodStringBuilder;
                $propertyName = $property->name;

                // Custom behavior: always add uppercase validation for strings
                $builder->regex('/^[A-Z]+$/', 'Must be uppercase');

                $validations = $property->validations;
                if ($validations && $validations->isFieldNullable()) {
                    $builder->nullable();
                }

                if ($property->isOptional && $validations && ! $validations->isFieldRequired()) {
                    $builder->optional();
                }

                return $builder;
            }

            public function getPriority(): int
            {
                return 1000; // Very high priority to override default string handler
            }
        };

        // Create generator with default registry from service provider
        $generator = $this->app->make(ValidationSchemaGenerator::class);

        // Add custom handler with high priority
        $generator->getTypeHandlerRegistry()->register($customStringHandler);

        $property = new SchemaPropertyData(
            name: 'code',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('code', [], 'string'),
        );

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            properties: new DataCollection(SchemaPropertyData::class, [$property]),
            className: 'TestClass',
            type: 'test',
        );

        $schema = $generator->generate($extracted);

        // Should use custom handler instead of default string handler
        $this->assertStringContainsString(
            "code: z.string().regex(/^[A-Z]+$/, 'Must be uppercase')",
            $schema
        );

        // Should NOT contain default string behavior (trim, min)
        $this->assertStringNotContainsString('.trim()', $schema);
        $this->assertStringNotContainsString('.min(', $schema);
    }

    #[Test]
    public function it_supports_validation_rule_based_handlers(): void
    {
        // Create a handler that handles any field with 'uuid' validation
        $uuidHandler = new class implements TypeHandlerInterface
        {
            public function canHandle(string $type): bool
            {
                return false; // Only handles based on validation rules
            }

            public function canHandleProperty(SchemaPropertyData $property): bool
            {
                return $property->validations->hasValidation('uuid');
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                $builder = new ZodStringBuilder;

                // Custom UUID validation - more specific than default
                $builder->regex('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', 'Must be valid UUID v4');

                $validations = $property->validations;
                if ($validations->isFieldNullable()) {
                    $builder->nullable();
                }

                if ($property->isOptional && ! $validations->isFieldRequired()) {
                    $builder->optional();
                }

                return $builder;
            }

            public function getPriority(): int
            {
                return 400; // Higher than default handlers
            }
        };

        $generator = $this->app->make(ValidationSchemaGenerator::class);
        $generator->getTypeHandlerRegistry()->register($uuidHandler);

        $property = new SchemaPropertyData(
            name: 'id',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('id', [
                new ResolvedValidation('uuid', [], null, false, false),
            ], 'string'),
        );

        $extracted = new ExtractedSchemaData(
            name: 'TestSchema',
            properties: new DataCollection(SchemaPropertyData::class, [$property]),
            className: 'TestClass',
            type: 'test',
        );

        $schema = $generator->generate($extracted);

        $this->assertStringContainsString(
            "id: z.string().regex(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i, 'Must be valid UUID v4')",
            $schema
        );
    }
}
