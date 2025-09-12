<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodFileBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class ZodFileBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_basic_file_validation(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateFile()->build();

        $this->assertEquals('z.file()', $result);
    }

    #[Test]
    public function it_builds_image_validation(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateImage()->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString(".mime(['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp']", $result);
    }

    #[Test]
    public function it_builds_file_with_mime_types(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateMimetypes(['application/pdf', 'text/plain'])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString(".mime(['application/pdf', 'text/plain']", $result);
    }

    #[Test]
    public function it_builds_file_with_extensions(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateExtensions(['pdf', 'docx', 'txt'])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString("['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain']", $result);
    }

    #[Test]
    public function it_builds_file_with_size_constraints(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateMin([100])->validateMax([2048])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString('.min(102400)', $result);
        $this->assertStringContainsString('.max(2097152)', $result);
    }

    #[Test]
    public function it_builds_file_with_exact_size(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateSize([500])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString('.min(512000)', $result);
        $this->assertStringContainsString('.max(512000)', $result);
    }

    #[Test]
    public function it_builds_file_with_size_between(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateBetween([100, 1000])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString('.min(102400)', $result);
        $this->assertStringContainsString('.max(1024000)', $result);
    }

    #[Test]
    public function it_builds_image_with_dimensions(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateImage()->validateDimensions([
            'min_width=100',
            'max_width=2000',
            'min_height=100',
            'max_height=2000',
        ])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString('refine((file) =>', $result);
        $this->assertStringContainsString('img.width >= 100', $result);
        $this->assertStringContainsString('img.width <= 2000', $result);
        $this->assertStringContainsString('img.height >= 100', $result);
        $this->assertStringContainsString('img.height <= 2000', $result);
    }

    #[Test]
    public function it_builds_image_with_exact_dimensions(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateImage()->validateDimensions([
            'width=800',
            'height=600',
        ])->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString('img.width === 800', $result);
        $this->assertStringContainsString('img.height === 600', $result);
    }

    #[Test]
    public function it_builds_image_with_aspect_ratio(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder
            ->validateImage()
            ->validateDimensions([
                'ratio=16/9',
            ])
            ->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString('Math.abs((img.width / img.height) - 1.7', $result); // 16/9 ≈ 1.777...
    }

    #[Test]
    public function it_handles_nullable_and_optional_file(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder->validateFile()
            ->nullable()
            ->optional()
            ->build();

        $this->assertEquals('z.file().nullable().optional()', $result);
    }

    #[Test]
    public function it_combines_multiple_file_validations(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder
            ->validateImage()
            ->validateMax([5120]) // 5MB
            ->validateExtensions(['jpg', 'jpeg', 'png'])
            ->build();

        $this->assertStringContainsString('z.file()', $result);
        $this->assertStringContainsString("['image/jpeg', 'image/png']", $result);
        $this->assertStringContainsString('.max(5242880', $result); // 5MB in bytes
    }

    #[Test]
    public function it_uses_custom_error_messages(): void
    {
        $builder = new ZodFileBuilder;

        $result = $builder
            ->validateImage([], 'Must be a valid image file')
            ->validateMax([1024], 'File too large')
            ->build();

        $this->assertStringContainsString("'Must be a valid image file'", $result);
        $this->assertStringContainsString("'File too large'", $result);
    }
}
