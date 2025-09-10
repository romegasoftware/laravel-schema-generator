<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests\TestLoginRequest;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class EnumZodGenerationTest extends TestCase
{
    protected RequestClassExtractor $extractor;
    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = $this->app->make(RequestClassExtractor::class);
        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_z_enum_for_laravel_enum_rule(): void
    {
        $reflection = new \ReflectionClass(TestLoginRequest::class);
        $extracted = $this->extractor->extract($reflection);
        $schema = $this->generator->generate($extracted, 'TestLoginRequestSchema');
        
        // The login_as_user_type field should generate a z.enum()
        $this->assertStringContainsString('login_as_user_type: z.enum(["Super Admin"])', $schema, 
            'Should generate z.enum() for Enum rule with only() filter');
        
        // Should be optional since there's no 'required' rule
        $this->assertStringContainsString('.optional()', $schema, 
            'Enum field should be optional when not required');
    }
}