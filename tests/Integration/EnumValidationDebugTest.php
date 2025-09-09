<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Orchestra\Testbench\Attributes\WithMigration;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class TestRequestForDebug extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => 'required|string|in:credit_card,paypal,bank_transfer',
        ];
    }
}

#[WithMigration]
class EnumValidationDebugTest extends TestCase
{
    protected ValidationSchemaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(ValidationSchemaGenerator::class);
    }

    public function test_debug_enum_generation()
    {
        $extractor = app(\RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor::class);
        $extracted = $extractor->extract(new \ReflectionClass(TestRequestForDebug::class));
        
        // Debug the extracted schema
        echo "\n\n=== EXTRACTED SCHEMA ===\n";
        foreach ($extracted->properties as $property) {
            echo "Property: {$property->name}\n";
            if ($property->validations) {
                echo "Inferred Type: {$property->validations->inferredType}\n";
                echo "Validations:\n";
                foreach ($property->validations->validations as $validation) {
                    echo "  - Rule: {$validation->rule}\n";
                    echo "    Parameters: " . json_encode($validation->parameters) . "\n";
                }
            }
            echo "\n";
        }
        
        $schema = $this->generator->generate($extracted, 'TestRequestForDebugSchema');
        
        echo "\n=== GENERATED SCHEMA ===\n";
        echo $schema;
        echo "\n\n";
        
        // This will fail but we'll see the output
        $this->assertStringContainsString("payment_method: z.enum(['credit_card', 'paypal', 'bank_transfer'])", $schema);
    }
}