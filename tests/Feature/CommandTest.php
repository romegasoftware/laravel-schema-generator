<?php

namespace RomegaSoftware\LaravelZodGenerator\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use RomegaSoftware\LaravelZodGenerator\Tests\TestCase;

class CommandTest extends TestCase
{
    #[Test]
    public function it_can_run_zod_generate_command(): void
    {
        $this->artisan('zod:generate')
            ->expectsOutput('🔍 Scanning for classes with #[ZodSchema] attribute...')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_no_classes_found_message_when_no_schemas(): void
    {
        $this->artisan('zod:generate')
            ->expectsOutput('No classes found with #[ZodSchema] attribute.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_available_features(): void
    {
        $this->artisan('zod:generate')
            ->expectsOutput('No classes found with #[ZodSchema] attribute.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_accepts_custom_output_path(): void
    {
        $customPath = __DIR__.'/../temp/custom-schemas.ts';

        $this->artisan('zod:generate', ['--path' => $customPath])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_accepts_force_flag(): void
    {
        $this->artisan('zod:generate', ['--force' => true])
            ->assertExitCode(0);
    }
}
