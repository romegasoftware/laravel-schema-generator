<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\Data;
use PHPUnit\Framework\Attributes\Test;

use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ResolvedValidationTest extends TestCase
{
    #[Test]
    public function it_creates_resolved_validation_with_basic_properties()
    {
        $validation = new ResolvedValidation('required');

        $this->assertEquals('required', $validation->rule);
        $this->assertEmpty($validation->parameters);
        $this->assertNull($validation->message);
        $this->assertFalse($validation->isRequired);
        $this->assertFalse($validation->isNullable);
    }

    #[Test]
    public function it_creates_resolved_validation_with_parameters()
    {
        $validation = new ResolvedValidation('min', [5]);

        $this->assertEquals('min', $validation->rule);
        $this->assertEquals([5], $validation->parameters);
        $this->assertEquals(5, $validation->getFirstParameter());
        $this->assertTrue($validation->hasParameters());
    }

    #[Test]
    public function it_creates_resolved_validation_with_custom_message()
    {
        $validation = new ResolvedValidation('required', [], 'This field is required');

        $this->assertEquals('This field is required', $validation->message);
        $this->assertTrue($validation->hasMessage());
    }

    #[Test]
    public function it_creates_required_validation()
    {
        $validation = new ResolvedValidation('required', [], null, true);

        $this->assertTrue($validation->isRequired);
        $this->assertFalse($validation->isNullable);
    }

    #[Test]
    public function it_creates_nullable_validation()
    {
        $validation = new ResolvedValidation('nullable', [], null, false, true);

        $this->assertFalse($validation->isRequired);
        $this->assertTrue($validation->isNullable);
    }

    #[Test]
    public function it_gets_specific_parameter_by_index()
    {
        $validation = new ResolvedValidation('between', [10, 20]);

        $this->assertEquals(10, $validation->getParameter(0));
        $this->assertEquals(20, $validation->getParameter(1));
        $this->assertNull($validation->getParameter(2));
    }

    #[Test]
    public function it_gets_all_parameters()
    {
        $validation = new ResolvedValidation('in', ['apple', 'banana', 'cherry']);

        $this->assertEquals(['apple', 'banana', 'cherry'], $validation->getParameters());
    }

    #[Test]
    public function it_handles_validation_without_parameters()
    {
        $validation = new ResolvedValidation('email');

        $this->assertFalse($validation->hasParameters());
        $this->assertNull($validation->getFirstParameter());
        $this->assertEmpty($validation->getParameters());
    }

    #[Test]
    public function it_handles_validation_without_custom_message()
    {
        $validation = new ResolvedValidation('required');

        $this->assertFalse($validation->hasMessage());
        $this->assertNull($validation->message);
    }
}
