<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class TypeHandlerRegistryTest extends TestCase
{
    protected TypeHandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new TypeHandlerRegistry;
    }

    #[Test]
    public function it_registers_and_retrieves_handlers(): void
    {
        $universalHandler = new UniversalTypeHandler;

        $this->registry->register($universalHandler);

        $handler = $this->registry->getHandler('string');
        $this->assertInstanceOf(UniversalTypeHandler::class, $handler);

        // UniversalTypeHandler handles properties with ResolvedValidationSet
        $emailProperty = new SchemaPropertyData(
            name: 'email',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('email', [
                new ResolvedValidation('email', [], null, false, false),
            ], 'email'),
        );
        $handler = $this->registry->getHandlerForProperty($emailProperty);
        $this->assertInstanceOf(UniversalTypeHandler::class, $handler);
    }

    #[Test]
    public function it_returns_null_for_unknown_types(): void
    {
        $handler = $this->registry->getHandler('unknown_type');
        $this->assertNull($handler);
    }

    #[Test]
    public function it_sorts_handlers_by_priority(): void
    {
        $lowPriorityHandler = $this->createMockHandler('test_type', 100);
        $highPriorityHandler = $this->createMockHandler('test_type', 200);

        $this->registry->register($lowPriorityHandler);
        $this->registry->register($highPriorityHandler);

        // Should return the high priority handler first
        $handler = $this->registry->getHandler('test_type');
        $this->assertSame($highPriorityHandler, $handler);
    }

    #[Test]
    public function it_handles_property_based_selection(): void
    {
        $propertyHandler = $this->createPropertyBasedHandler();
        $this->registry->register($propertyHandler);

        $property = new SchemaPropertyData(
            name: 'test',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('test', [
                new ResolvedValidation('email', [], null, false, false),
            ], 'email'),
        );

        $handler = $this->registry->getHandlerForProperty($property);
        $this->assertSame($propertyHandler, $handler);
    }

    #[Test]
    public function it_can_register_multiple_handlers_at_once(): void
    {
        $universalHandler = new UniversalTypeHandler;
        $customHandler = $this->createMockHandler('custom_type', 100);

        $this->registry->registerMany([$universalHandler, $customHandler]);

        $this->assertInstanceOf(UniversalTypeHandler::class, $this->registry->getHandler('string'));

        // UniversalTypeHandler handles properties with ResolvedValidationSet
        $emailProperty = new SchemaPropertyData(
            name: 'email',
            validator: null,
            isOptional: false,
            validations: ResolvedValidationSet::make('email', [
                new ResolvedValidation('email', [], null, false, false),
            ], 'email'),
        );
        $this->assertInstanceOf(UniversalTypeHandler::class, $this->registry->getHandlerForProperty($emailProperty));
    }

    #[Test]
    public function it_can_clear_all_handlers(): void
    {
        $this->registry->register(new UniversalTypeHandler);
        $this->assertInstanceOf(UniversalTypeHandler::class, $this->registry->getHandler('string'));

        $this->registry->clear();
        $this->assertNull($this->registry->getHandler('string'));
    }

    protected function createMockHandler(string $type, int $priority): TypeHandlerInterface
    {
        return new class($type, $priority) implements TypeHandlerInterface
        {
            public function __construct(private string $type, private int $priority) {}

            public function canHandle(string $type): bool
            {
                return $type === $this->type;
            }

            public function canHandleProperty(SchemaPropertyData $property): bool
            {
                return false; // This handler only handles by type
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                return new ZodStringBuilder;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }

    protected function createPropertyBasedHandler(): TypeHandlerInterface
    {
        return new class implements TypeHandlerInterface
        {
            public function canHandle(string $type): bool
            {
                return false;
            }

            public function canHandleProperty(SchemaPropertyData $property): bool
            {
                return $property->validations instanceof ResolvedValidationSet &&
                       $property->validations->hasValidation('email');
            }

            public function handle(SchemaPropertyData $property): ZodBuilder
            {
                return new ZodStringBuilder;
            }

            public function getPriority(): int
            {
                return 300;
            }
        };
    }
}
