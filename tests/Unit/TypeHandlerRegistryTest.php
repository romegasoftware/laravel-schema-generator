<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelZodGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelZodGenerator\Data\ValidationRules\StringValidationRules;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\EmailTypeHandler;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\StringTypeHandler;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerInterface;
use RomegaSoftware\LaravelZodGenerator\TypeHandlers\TypeHandlerRegistry;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodBuilder;
use RomegaSoftware\LaravelZodGenerator\ZodBuilders\ZodStringBuilder;

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
        $stringHandler = new StringTypeHandler;
        $emailHandler = new EmailTypeHandler;

        $this->registry->register($stringHandler);
        $this->registry->register($emailHandler);

        $handler = $this->registry->getHandler('string');
        $this->assertInstanceOf(StringTypeHandler::class, $handler);

        // EmailTypeHandler doesn't handle 'email' type directly, it handles properties with email validation
        $emailProperty = new SchemaPropertyData(
            name: 'email',
            type: 'string',
            isOptional: false,
            validations: new StringValidationRules(email: true),
        );
        $handler = $this->registry->getHandlerForProperty($emailProperty);
        $this->assertInstanceOf(EmailTypeHandler::class, $handler);
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
            type: 'string',
            isOptional: false,
            validations: new StringValidationRules(email: true),
        );

        $handler = $this->registry->getHandlerForProperty($property);
        $this->assertSame($propertyHandler, $handler);
    }

    #[Test]
    public function it_can_register_multiple_handlers_at_once(): void
    {
        $stringHandler = new StringTypeHandler;
        $emailHandler = new EmailTypeHandler;

        $this->registry->registerMany([$stringHandler, $emailHandler]);

        $this->assertInstanceOf(StringTypeHandler::class, $this->registry->getHandler('string'));

        // EmailTypeHandler doesn't handle 'email' type directly, test with property
        $emailProperty = new SchemaPropertyData(
            name: 'email',
            type: 'string',
            isOptional: false,
            validations: new StringValidationRules(email: true),
        );
        $this->assertInstanceOf(EmailTypeHandler::class, $this->registry->getHandlerForProperty($emailProperty));
    }

    #[Test]
    public function it_can_clear_all_handlers(): void
    {
        $this->registry->register(new StringTypeHandler);
        $this->assertInstanceOf(StringTypeHandler::class, $this->registry->getHandler('string'));

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
                return $this->canHandle($property->type);
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
                return $property->validations->email;
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
