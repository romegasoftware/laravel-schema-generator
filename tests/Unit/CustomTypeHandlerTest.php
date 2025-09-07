<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelZodGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\StringValidationRules;
use RomegaSoftware\LaravelZodGenerator\Generators\ZodSchemaGenerator;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;
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
                return $this->canHandle($property->type);
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                $builder = new ZodStringBuilder;

                // Custom datetime validation - requires ISO format
                $builder->regex('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', 'Must be valid ISO datetime');

                // Handle nullable/optional
                $validations = $property->validations;
                if ($validations->nullable) {
                    $builder->nullable();
                }

                if ($property->isOptional && ! $validations->required) {
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
        $generator = new ZodSchemaGenerator($registry);

        $property = new SchemaPropertyData(
            name: 'created_at',
            type: 'datetime',
            isOptional: false,
            validations: new StringValidationRules,
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
                return $this->canHandle($property->type);
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                $builder = new ZodStringBuilder;
                $propertyName = $property->name;

                // Custom behavior: always add uppercase validation for strings
                $builder->regex('/^[A-Z]+$/', 'Must be uppercase');

                $validations = $property->validations;
                if ($validations->nullable) {
                    $builder->nullable();
                }

                if ($property->isOptional && ! $validations->required) {
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
        $generator = $this->app->make(ZodSchemaGenerator::class);

        // Add custom handler with high priority
        $generator->getTypeHandlerRegistry()->register($customStringHandler);

        $property = new SchemaPropertyData(
            name: 'code',
            type: 'string',
            isOptional: false,
            validations: new StringValidationRules,
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
                return $property->validations->uuid;
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                $builder = new ZodStringBuilder;

                // Custom UUID validation - more specific than default
                $builder->regex('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', 'Must be valid UUID v4');

                $validations = $property->validations;
                if ($validations->nullable) {
                    $builder->nullable();
                }

                if ($property->isOptional && ! $validations->required) {
                    $builder->optional();
                }

                return $builder;
            }

            public function getPriority(): int
            {
                return 400; // Higher than default handlers
            }
        };

        $generator = $this->app->make(ZodSchemaGenerator::class);
        $generator->getTypeHandlerRegistry()->register($uuidHandler);

        $property = new SchemaPropertyData(
            name: 'id',
            type: 'string',
            isOptional: false,
            validations: new StringValidationRules(
                uuid: true,
            ),
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
