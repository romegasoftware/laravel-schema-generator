<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\InheritedPostalCodeData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses\InheritedPostalCodeRuntimeDisabledData;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

class RuntimeInheritedValidationTest extends TestCase
{
    #[Test]
    public function it_enforces_inherited_rules_during_validation(): void
    {
        $resolver = $this->app->make(DataValidatorResolver::class);

        $missing = $resolver->execute(InheritedPostalCodeData::class, []);
        $this->assertTrue($missing->fails());
        $this->assertArrayHasKey('Required', $missing->failed()['postal_code']);

        $invalid = $resolver->execute(InheritedPostalCodeData::class, ['postal_code' => 'ABCDE']);
        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('Regex', $invalid->failed()['postal_code']);
        $this->assertSame(
            'Postal code must look like 12345 or 12345-6789',
            $invalid->errors()->first('postal_code')
        );

        $valid = $resolver->execute(InheritedPostalCodeData::class, ['postal_code' => '12345-6789']);
        $this->assertFalse($valid->fails());
    }

    #[Test]
    public function it_can_disable_runtime_inheritance_per_attribute(): void
    {
        $resolver = $this->app->make(DataValidatorResolver::class);

        $missing = $resolver->execute(InheritedPostalCodeRuntimeDisabledData::class, []);
        $this->assertFalse($missing->fails());

        $invalid = $resolver->execute(
            InheritedPostalCodeRuntimeDisabledData::class,
            ['postal_code' => 'ABCDE']
        );
        $this->assertFalse($invalid->fails());
    }
}
