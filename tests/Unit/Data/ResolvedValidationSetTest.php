<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\Data;
use PHPUnit\Framework\Attributes\Test;

use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ResolvedValidationSetTest extends TestCase
{
    #[Test]
    public function it_creates_validation_set_from_validations_array()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('string'),
            new ResolvedValidation('min', [3]),
        ];

        $validationSet = ResolvedValidationSet::make('username', $validations, 'string');

        $this->assertEquals('username', $validationSet->fieldName);
        $this->assertEquals('string', $validationSet->inferredType);
        $this->assertTrue($validationSet->isFieldRequired());
        $this->assertFalse($validationSet->isFieldNullable());
        $this->assertCount(3, $validationSet->validations);
    }

    #[Test]
    public function it_detects_required_field_from_validations()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('string'),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertTrue($validationSet->isRequired);
        $this->assertTrue($validationSet->isFieldRequired());
    }

    #[Test]
    public function it_detects_nullable_field_from_validations()
    {
        $validations = [
            new ResolvedValidation('nullable', [], null, false, true),
            new ResolvedValidation('string'),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertTrue($validationSet->isNullable);
        $this->assertTrue($validationSet->isFieldNullable());
    }

    #[Test]
    public function it_checks_if_validation_exists()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('min', [5]),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertTrue($validationSet->hasValidation('required'));
        $this->assertTrue($validationSet->hasValidation('min'));
        $this->assertFalse($validationSet->hasValidation('max'));
    }

    #[Test]
    public function it_gets_specific_validation()
    {
        $minValidation = new ResolvedValidation('min', [5], 'Too short');
        $validations = [
            new ResolvedValidation('required', [], null, true),
            $minValidation,
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $retrieved = $validationSet->getValidation('min');
        $this->assertSame($minValidation, $retrieved);
        $this->assertNull($validationSet->getValidation('max'));
    }

    #[Test]
    public function it_gets_custom_message_for_rule()
    {
        $validations = [
            new ResolvedValidation('required', [], 'This field is required'),
            new ResolvedValidation('min', [5], 'Too short'),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertEquals('This field is required', $validationSet->getMessage('required'));
        $this->assertEquals('Too short', $validationSet->getMessage('min'));
        $this->assertNull($validationSet->getMessage('max'));
    }

    #[Test]
    public function it_gets_validation_parameter()
    {
        $validations = [
            new ResolvedValidation('min', [5]),
            new ResolvedValidation('between', [10, 20]),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertEquals(5, $validationSet->getValidationParameter('min'));
        $this->assertEquals(10, $validationSet->getValidationParameter('between'));
        $this->assertNull($validationSet->getValidationParameter('max'));
    }

    #[Test]
    public function it_gets_validation_parameters()
    {
        $validations = [
            new ResolvedValidation('in', ['apple', 'banana', 'cherry']),
            new ResolvedValidation('between', [10, 20]),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertEquals(['apple', 'banana', 'cherry'], $validationSet->getValidationParameters('in'));
        $this->assertEquals([10, 20], $validationSet->getValidationParameters('between'));
        $this->assertEquals([], $validationSet->getValidationParameters('max'));
    }

    #[Test]
    public function it_gets_rule_names()
    {
        $validations = [
            new ResolvedValidation('required', [], null, true),
            new ResolvedValidation('string'),
            new ResolvedValidation('min', [3]),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);

        $this->assertEquals(['required', 'string', 'min'], $validationSet->getRuleNames());
    }

    #[Test]
    public function it_converts_to_validation_array_for_backward_compatibility()
    {
        $validations = [
            new ResolvedValidation('required', [], 'Field is required', true),
            new ResolvedValidation('string'),
            new ResolvedValidation('min', [5], 'Too short'),
            new ResolvedValidation('max', [100]),
            new ResolvedValidation('in', ['apple', 'banana']),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);
        $array = $validationSet->toValidationArray();

        $this->assertTrue($array['required']);
        $this->assertFalse($array['nullable']);
        $this->assertTrue($array['string']);
        $this->assertEquals(5, $array['min']);
        $this->assertEquals(100, $array['max']);
        $this->assertEquals(['apple', 'banana'], $array['in']);

        $this->assertEquals([
            'required' => 'Field is required',
            'min' => 'Too short',
        ], $array['customMessages']);
    }

    #[Test]
    public function it_gets_multiple_validations_with_same_rule()
    {
        $validations = [
            new ResolvedValidation('custom_rule', ['param1']),
            new ResolvedValidation('custom_rule', ['param2']),
            new ResolvedValidation('string'),
        ];

        $validationSet = ResolvedValidationSet::make('field', $validations);
        $customRuleValidations = $validationSet->getValidations('custom_rule');

        $this->assertCount(2, $customRuleValidations);
        $this->assertEquals('param1', $customRuleValidations[0]->getFirstParameter());
        $this->assertEquals('param2', $customRuleValidations[1]->getFirstParameter());
    }

    #[Test]
    public function it_handles_empty_validations()
    {
        $validationSet = ResolvedValidationSet::make('field', [], 'string');

        $this->assertFalse($validationSet->isFieldRequired());
        $this->assertFalse($validationSet->isFieldNullable());
        $this->assertEmpty($validationSet->getRuleNames());
        $this->assertCount(0, $validationSet->validations);
    }
}
