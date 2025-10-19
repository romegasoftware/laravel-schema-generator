<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

enum SampleStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

class CustomRuleObject {}

use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ValidationRuleFactory;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ValidationRuleFactoryTest extends TestCase
{
    #[Test]
    public function it_ignores_empty_rule_objects_when_normalizing(): void
    {
        $factory = new ValidationRuleFactory;

        $normalized = $factory->normalizeRule([
            'nullable',
            'string',
            'max:255',
            Rule::requiredIf(fn () => false),
        ]);

        $this->assertSame('nullable|string|max:255', $normalized);
    }

    #[Test]
    public function it_preserves_rule_objects_that_evaluate_to_strings(): void
    {
        $factory = new ValidationRuleFactory;

        $normalized = $factory->normalizeRule([
            'nullable',
            Rule::requiredIf(fn () => true),
        ]);

        $this->assertSame('nullable|required', $normalized);
    }

    #[Test]
    public function it_converts_required_if_rule_objects_with_request_comparisons(): void
    {
        $factory = new ValidationRuleFactory;

        $closure = fn (): bool => request()->input('status') === 'shipped';

        $normalized = $factory->normalizeRule([
            'nullable',
            'string',
            Rule::requiredIf($closure),
        ]);

        $this->assertSame('nullable|string|required_if:status,shipped', $normalized);
    }

    #[Test]
    public function it_normalizes_prohibited_if_rule_objects_with_request_closures(): void
    {
        $factory = new ValidationRuleFactory;

        $normalized = $factory->normalizeRule([
            'string',
            Rule::prohibitedIf(fn (): bool => request()->input('status') === 'shipped'),
        ]);

        $this->assertSame('string|prohibited_if:status,shipped', $normalized);
    }

    #[Test]
    public function it_normalizes_nested_array_rules(): void
    {
        $factory = new ValidationRuleFactory;

        $rules = [
            ' required ',
            [
                ' nullable ',
                'string ',
            ],
            Rule::requiredIf(fn (): bool => true),
            Rule::prohibitedIf(fn (): bool => true),
            '',
        ];

        $normalized = $factory->normalizeRule($rules);

        $this->assertSame('required|nullable|string|required|prohibited', $normalized);
    }

    #[Test]
    public function it_normalizes_object_rules_via_string_cast(): void
    {
        $factory = new ValidationRuleFactory;

        $ruleObject = new class
        {
            public function __toString(): string
            {
                return 'custom_rule';
            }
        };

        $this->assertSame('custom_rule', $factory->normalizeRule($ruleObject));
    }

    #[Test]
    public function it_returns_empty_string_for_unsupported_rule_types(): void
    {
        $factory = new ValidationRuleFactory;

        $this->assertSame('', $factory->normalizeRule(123));
        $this->assertSame('', $factory->normalizeRule(null));
    }

    #[Test]
    public function it_resolves_object_rules_via_string_cast(): void
    {
        $factory = new ValidationRuleFactory;

        $rule = new class
        {
            public function __toString(): string
            {
                return 'object_rule';
            }
        };

        $this->assertSame('object_rule', $factory->resolveRuleObject($rule));
    }

    #[Test]
    public function it_resolves_required_and_prohibited_if_rule_objects(): void
    {
        $factory = new ValidationRuleFactory;

        $this->assertSame('required', $factory->resolveRuleObject(Rule::requiredIf(true)));
        $this->assertSame('', $factory->resolveRuleObject(Rule::requiredIf(false)));
        $this->assertSame('prohibited', $factory->resolveRuleObject(Rule::prohibitedIf(true)));
        $this->assertSame('', $factory->resolveRuleObject(Rule::prohibitedIf(false)));
    }

    #[Test]
    public function it_resolves_enum_rule_to_in_rule(): void
    {
        $factory = new ValidationRuleFactory;

        $enumRule = new \Illuminate\Validation\Rules\Enum(SampleStatusEnum::class);

        $this->assertSame('in:active,inactive', $factory->resolveRuleObject($enumRule));
    }

    #[Test]
    public function it_resolves_enum_rule_with_only_values(): void
    {
        $factory = new ValidationRuleFactory;

        $enumRule = (new \Illuminate\Validation\Rules\Enum(SampleStatusEnum::class))
            ->only(SampleStatusEnum::ACTIVE);

        $this->assertSame(['active'], $factory->extractEnumValues($enumRule));
    }

    #[Test]
    public function it_handles_invalid_enum_rule_by_returning_enum_keyword(): void
    {
        $factory = new ValidationRuleFactory;

        $enumRule = new \Illuminate\Validation\Rules\Enum(\stdClass::class);

        $this->assertSame('enum', $factory->resolveRuleObject($enumRule));
    }

    #[Test]
    public function it_handles_enum_extraction_errors_gracefully(): void
    {
        $factory = new ValidationRuleFactory;

        $enumRule = new \Illuminate\Validation\Rules\Enum(SampleStatusEnum::class);

        $reflection = new \ReflectionClass($enumRule);
        $typeProperty = $reflection->getProperty('type');
        $typeProperty->setAccessible(true);
        $typeProperty->setValue($enumRule, null);

        $this->assertSame([], $factory->extractEnumValues($enumRule));
    }

    #[Test]
    public function it_falls_back_to_short_class_name_for_unknown_rule_objects(): void
    {
        $factory = new ValidationRuleFactory;

        $this->assertSame('customruleobject', $factory->resolveRuleObject(new CustomRuleObject));
    }
}
