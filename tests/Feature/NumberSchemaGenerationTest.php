<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class TestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'multiple_of' => 'integer|multiple_of:5',
        ];
    }
}

class NumberSchemaGenerationTest extends TestCase
{
    use InteractsWithExtractors;

    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = $this->app->make(ValidationSchemaGenerator::class);
    }

    #[Test]
    public function it_generates_schema_for_test_request(): void
    {
        $extracted = $this->getRequestExtractor()->extract(new \ReflectionClass(TestRequest::class));

        $this->assertInstanceOf(ExtractedSchemaData::class, $extracted);
        $this->assertEquals('TestRequestSchema', $extracted->name);

        $schema = $this->generator->generate($extracted);

        // Check that the schema contains file validations
        $this->assertStringContainsString('z.object({', $schema);

        $this->assertStringContainsString('multiple_of: z.number(', $schema);
        $this->assertStringContainsString('.multipleOf(5,', $schema);
    }
}
