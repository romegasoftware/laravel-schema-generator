<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodArrayBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodInlineObjectBuilder;
use RomegaSoftware\LaravelSchemaGenerator\ZodBuilders\ZodStringBuilder;

class ZodArrayBuilderDependencyInjectionTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_injected_dependencies(): void
    {
        // Create mock dependencies
        $factory = Mockery::mock(ZodBuilderFactory::class);
        $universalTypeHandler = Mockery::mock(UniversalTypeHandler::class);

        // Create ZodArrayBuilder with dependency injection
        $arrayBuilder = new ZodArrayBuilder('z.string()', $factory, $universalTypeHandler);

        // Verify the array builder was created successfully
        $this->assertInstanceOf(ZodArrayBuilder::class, $arrayBuilder);
        $this->assertEquals('z.string()', $arrayBuilder->getItemType());
    }

    #[Test]
    public function it_uses_factory_to_create_nested_array_builders(): void
    {
        // Create mock dependencies
        $factory = Mockery::mock(ZodBuilderFactory::class);
        $universalTypeHandler = Mockery::mock(UniversalTypeHandler::class);
        $mockArrayBuilder = Mockery::mock(ZodArrayBuilder::class);

        // Set up expectations
        $factory->shouldReceive('createArrayBuilder')
            ->once()
            ->andReturn($mockArrayBuilder);

        $mockArrayBuilder->shouldReceive('ofBuilder')
            ->once()
            ->with(Mockery::any())
            ->andReturn($mockArrayBuilder);

        // Create test data
        $validations = ResolvedValidationSet::make('test', [], 'string');
        $property = new SchemaPropertyData('test', null, false, $validations);

        // Create ZodArrayBuilder with mocked dependencies
        $arrayBuilder = new ZodArrayBuilder('z.any()', $factory, $universalTypeHandler);
        $arrayBuilder->setProperty($property);

        // Create nested validations with proper construction
        $nestedValidations = ResolvedValidationSet::make('nested', [], 'string');

        // Mock the property validations method
        $mockValidations = Mockery::mock(ResolvedValidationSet::class);
        $mockValidations->shouldReceive('getNestedValidations')
            ->andReturn($nestedValidations);

        $mockValidations->shouldReceive('hasObjectProperties')
            ->andReturn(false);

        // Set up type handler expectations
        $universalTypeHandler->shouldReceive('setProperty')
            ->once()
            ->with(Mockery::type(SchemaPropertyData::class))
            ->andReturn($universalTypeHandler);

        $universalTypeHandler->shouldReceive('createBuilderForType')
            ->once()
            ->andReturn(new ZodStringBuilder);

        $universalTypeHandler->shouldReceive('applyValidations')
            ->once();

        $property->validations = $mockValidations;

        // Call the method that should use the factory
        $result = $arrayBuilder->setup();

        // Verify the factory was used
        $this->assertSame($mockArrayBuilder, $result);
    }

    #[Test]
    public function it_uses_factory_to_create_inline_object_builders(): void
    {
        // Create mock dependencies
        $factory = Mockery::mock(ZodBuilderFactory::class);
        $universalTypeHandler = Mockery::mock(UniversalTypeHandler::class);
        $mockInlineObjectBuilder = Mockery::mock(ZodInlineObjectBuilder::class);

        // Set up expectations
        $factory->shouldReceive('createInlineObjectBuilder')
            ->once()
            ->andReturn($mockInlineObjectBuilder);

        $mockInlineObjectBuilder->shouldReceive('property')
            ->once()
            ->with('testProp', Mockery::any());

        // Set up type handler expectations
        $universalTypeHandler->shouldReceive('setProperty')
            ->once()
            ->with(Mockery::type(SchemaPropertyData::class))
            ->andReturn($universalTypeHandler);

        $universalTypeHandler->shouldReceive('createBuilderForType')
            ->once()
            ->andReturn(new ZodStringBuilder);

        $universalTypeHandler->shouldReceive('applyValidations')
            ->once();

        // Create ZodArrayBuilder with mocked dependencies
        $arrayBuilder = new ZodArrayBuilder('z.any()', $factory, $universalTypeHandler);

        // Create test validation set
        $validationSet = ResolvedValidationSet::make('testProp', [], 'string');
        $objectProperties = ['testProp' => $validationSet];

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($arrayBuilder);
        $method = $reflection->getMethod('createObjectBuilderFromProperties');
        $method->setAccessible(true);

        $result = $method->invoke($arrayBuilder, $objectProperties);

        // Verify the factory was used
        $this->assertSame($mockInlineObjectBuilder, $result);
    }

    #[Test]
    public function factory_creates_correct_builder_types(): void
    {
        $factory = app(ZodBuilderFactory::class);
        $universalTypeHandler = app(UniversalTypeHandler::class);
        $factory->setUniversalTypeHandler($universalTypeHandler);

        // Test array builder creation
        $arrayBuilder = $factory->createArrayBuilder();
        $this->assertInstanceOf(ZodArrayBuilder::class, $arrayBuilder);

        // Test inline object builder creation
        $inlineObjectBuilder = $factory->createInlineObjectBuilder();
        $this->assertInstanceOf(ZodInlineObjectBuilder::class, $inlineObjectBuilder);

        // Factory doesn't expose UniversalTypeHandler directly anymore
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
