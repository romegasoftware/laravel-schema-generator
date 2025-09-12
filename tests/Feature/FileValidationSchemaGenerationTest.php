<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Feature;

use Illuminate\Foundation\Http\FormRequest;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Data\ExtractedSchemaData;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Generators\ValidationSchemaGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class TestUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => 'required|file|image|max:2048',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'photo' => 'file|image|dimensions:min_width=100,max_width=2000,min_height=100,max_height=2000',
            'resume' => 'file|extensions:pdf,docx,txt|between:10,5120',
            'thumbnail' => 'file|image|dimensions:width=800,height=600,ratio=4/3',
        ];
    }
}

class FileValidationSchemaGenerationTest extends TestCase
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
    public function it_generates_schema_for_file_upload_validation(): void
    {
        $extracted = $this->extractor->extract(new \ReflectionClass(TestUploadRequest::class));

        $this->assertInstanceOf(ExtractedSchemaData::class, $extracted);
        $this->assertEquals('TestUploadRequestSchema', $extracted->name);

        $schema = $this->generator->generate($extracted);

        // Check that the schema contains file validations
        $this->assertStringContainsString('z.object({', $schema);

        // Avatar field should be required file image with max size
        $this->assertStringContainsString('avatar:', $schema);
        $this->assertStringContainsString('z.file()', $schema);

        // Document field should be nullable file with mimes and max
        $this->assertStringContainsString('document:', $schema);

        // Photo should have dimensions
        $this->assertStringContainsString('photo:', $schema);

        // Resume should have extensions and between
        $this->assertStringContainsString('resume:', $schema);

        // Thumbnail should have exact dimensions and ratio
        $this->assertStringContainsString('thumbnail:', $schema);
    }

    #[Test]
    public function it_handles_image_validation_correctly(): void
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'profile_pic' => 'required|image|max:1024',
                ];
            }
        };

        $extracted = $this->extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted);

        // Should detect image rule and create file builder
        $this->assertStringContainsString('z.file()', $schema);
        $this->assertStringContainsString("['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp']", $schema);
        $this->assertStringContainsString('.max(1048576', $schema); // 1024KB in bytes
    }

    #[Test]
    public function it_allows_svgs_correctly(): void
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'profile_pic' => 'required|image:allow_svg',
                ];
            }
        };

        $extracted = $this->extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted);

        // Should detect image rule and create file builder
        $this->assertStringContainsString('z.file()', $schema);
        $this->assertStringContainsString("['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/svg+xml']", $schema);
    }

    #[Test]
    public function it_handles_file_with_mimes_validation(): void
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'attachment' => 'file|mimes:pdf,csv,xlsx',
                ];
            }
        };

        $extracted = $this->extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('z.file()', $schema);
        // Should convert extensions to MIME types
        $this->assertStringContainsString('application/pdf', $schema);
    }

    #[Test]
    public function it_handles_file_with_size_between(): void
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'media' => 'file|between:100,5000',  // Added 'file' rule to properly identify as file type
                ];
            }
        };

        $extracted = $this->extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('z.file()', $schema);
        $this->assertStringContainsString('.min(102400', $schema); // 100KB
        $this->assertStringContainsString('.max(5120000', $schema); // 5000KB
    }

    #[Test]
    public function it_handles_optional_file_fields(): void
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'optional_file' => 'nullable|file|max:2048',
                ];
            }
        };

        $extracted = $this->extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('z.file()', $schema);
        $this->assertStringContainsString('.nullable()', $schema);
        $this->assertStringContainsString('.optional()', $schema);
    }

    #[Test]
    public function it_handles_image_with_dimensions(): void
    {
        $request = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'banner' => 'image|dimensions:min_width=1920,min_height=1080,ratio=16/9',
                ];
            }
        };

        $extracted = $this->extractor->extract(new \ReflectionClass($request));
        $schema = $this->generator->generate($extracted);

        $this->assertStringContainsString('z.file()', $schema);
        $this->assertStringContainsString('refine((file) =>', $schema);
        $this->assertStringContainsString('img.width >= 1920', $schema);
        $this->assertStringContainsString('img.height >= 1080', $schema);
        $this->assertStringContainsString('Math.abs((img.width / img.height) - 1.7', $schema); // 16/9 ratio
    }
}
