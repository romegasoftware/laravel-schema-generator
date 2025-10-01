<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\Validation\InlineRulesClass;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class InlineRulesClassExtractionTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_extracts_rules_from_plain_php_classes(): void
    {
        $reflection = new ReflectionClass(InlineRulesClass::class);

        $extracted = $this->getRequestExtractor()->extract($reflection);

        $properties = $extracted->properties->toCollection()->keyBy('name');

        $this->assertTrue($properties->has('email'));
        $this->assertTrue($properties->has('role'));

        $email = $properties->get('email');
        $this->assertSame('email', $email->name);
        $this->assertTrue($email->validations->hasValidation('Email'));
        $this->assertSame('Email is required', $email->validations->getMessage('Required'));

        $role = $properties->get('role');
        $this->assertSame('role', $role->name);
        $this->assertTrue($role->validations->hasValidation('RequiredIf'));
        $this->assertSame('The User role field is required when email is admin@example.com.', $role->validations->getMessage('RequiredIf'));
        $this->assertTrue($role->isOptional);
    }
}
