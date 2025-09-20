<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit\Builders;

use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodFileBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

final class ValidatesSizeAttributesTest extends TestCase
{
    #[Test]
    public function it_applies_min_and_max_constraints_for_strings(): void
    {
        $builder = new ZodStringBuilder;
        $builder->setTranslator($this->translator([
            'validation.between' => 'string between message',
        ]));
        $builder->setFieldName('title');
        $builder->validateBetween([2, 5]);

        $schema = $builder->build();

        $this->assertStringContainsString('z.string()', $schema);
        $this->assertStringContainsString(".min(2, 'string between message')", $schema);
        $this->assertStringContainsString(".max(5, 'string between message')", $schema);
    }

    #[Test]
    public function it_applies_min_and_max_constraints_for_numbers(): void
    {
        $builder = new ZodNumberBuilder;
        $builder->setTranslator($this->translator([
            'validation.between' => 'number between message',
        ]));
        $builder->setFieldName('amount');
        $builder->validateBetween([10, 20]);

        $schema = $builder->build();

        $this->assertStringContainsString('z.number()', $schema);
        $this->assertStringContainsString(".min(10, 'number between message')", $schema);
        $this->assertStringContainsString(".max(20, 'number between message')", $schema);
    }

    #[Test]
    public function it_applies_min_and_max_constraints_for_arrays(): void
    {
        $builder = new class extends ZodBuilder
        {
            protected function getBaseType(): string
            {
                return 'z.array(z.string())';
            }
        };

        $builder->setTranslator($this->translator([
            'validation.between' => 'array between message',
        ]));
        $builder->setFieldName('tags');
        $builder->validateBetween([1, 3]);

        $schema = $builder->build();

        $this->assertStringContainsString('z.array(z.string())', $schema);
        $this->assertStringContainsString(".min(1, 'array between message')", $schema);
        $this->assertStringContainsString(".max(3, 'array between message')", $schema);
    }

    #[Test]
    public function it_converts_file_size_rules_from_kilobytes_to_bytes(): void
    {
        $builder = new ZodFileBuilder;
        $builder->setTranslator($this->translator([
            'validation.min' => 'file min message',
            'validation.max' => 'file max message',
        ]));
        $builder->setFieldName('upload');
        $builder->validateMin([5]);
        $builder->validateMax([10]);

        $schema = $builder->build();

        $this->assertStringContainsString('z.file()', $schema);
        $this->assertStringContainsString(".min(5120, 'file min message')", $schema);
        $this->assertStringContainsString(".max(10240, 'file max message')", $schema);
    }

    private function translator(array $lines): Translator
    {
        return new class($lines) implements Translator
        {
            public function __construct(private array $lines, private string $locale = 'en') {}

            public function get($key, array $replace = [], $locale = null)
            {
                return $this->lines[$key] ?? $key;
            }

            public function choice($key, $number, array $replace = [], $locale = null)
            {
                return $this->get($key, $replace, $locale);
            }

            public function getLocale()
            {
                return $this->locale;
            }

            public function setLocale($locale)
            {
                $this->locale = $locale;
            }
        };
    }
}
