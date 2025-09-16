<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Factories;

use Illuminate\Contracts\Translation\Translator;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodAnyBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodArrayBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodBooleanBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEmailBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodEnumBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodFileBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodInlineObjectBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodNumberBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodObjectBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodPasswordBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\ZodStringBuilder;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\BuilderInterface;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;
use RomegaSoftware\LaravelSchemaGenerator\TypeHandlers\UniversalTypeHandler;

/**
 * Factory class for creating ZodBuilder instances with proper dependency injection
 */
class ZodBuilderFactory
{
    use Makeable;

    private ?UniversalTypeHandler $universalTypeHandler = null;

    public function __construct(
        private ?Translator $translator = null
    ) {}

    /**
     * Set the universal type handler for complex builders
     */
    public function setUniversalTypeHandler(UniversalTypeHandler $universalTypeHandler): void
    {
        $this->universalTypeHandler = $universalTypeHandler;
    }

    /**
     * Helper method to set translator on builders
     *
     * @template TBuilder of BuilderInterface
     *
     * @param  TBuilder  $builder
     * @return TBuilder
     */
    private function setTranslatorOnBuilder(BuilderInterface $builder): BuilderInterface
    {
        $builder->setTranslator($this->translator);

        return $builder;
    }

    /**
     * Create a ZodArrayBuilder instance
     */
    public function createArrayBuilder(string $itemType = 'z.any()'): ZodArrayBuilder
    {
        if ($this->universalTypeHandler === null) {
            throw new \InvalidArgumentException('UniversalTypeHandler must be set before creating array builders. Call setUniversalTypeHandler() first.');
        }

        return $this->setTranslatorOnBuilder(new ZodArrayBuilder($itemType, $this, $this->universalTypeHandler));
    }

    /**
     * Create a ZodInlineObjectBuilder instance
     */
    public function createInlineObjectBuilder(): ZodInlineObjectBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodInlineObjectBuilder($this->universalTypeHandler));
    }

    /**
     * Create a ZodBooleanBuilder instance
     */
    public function createBooleanBuilder(): ZodBooleanBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodBooleanBuilder);
    }

    /**
     * Create a ZodStringBuilder instance
     */
    public function createStringBuilder(): ZodStringBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodStringBuilder);
    }

    /**
     * Create a ZodNumberBuilder instance
     */
    public function createNumberBuilder(): ZodNumberBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodNumberBuilder);
    }

    /**
     * Create a ZodEnumBuilder instance
     */
    public function createEnumBuilder(): ZodEnumBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodEnumBuilder);
    }

    /**
     * Create a ZodEmailBuilder instance
     */
    public function createEmailBuilder(): ZodEmailBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodEmailBuilder);
    }

    /**
     * Create a ZodAnyBuilder instance
     */
    public function createAnyBuilder(): ZodAnyBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodAnyBuilder);
    }

    /**
     * Create a ZodObjectBuilder instance
     */
    public function createObjectBuilder(string $schemaReference = ''): ZodObjectBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodObjectBuilder($schemaReference));
    }

    /**
     * Create a ZodFileBuilder instance
     */
    public function createFileBuilder(): ZodFileBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodFileBuilder);
    }

    /**
     * Create a ZodPasswordBuilder instance
     */
    public function createPasswordBuilder(): ZodPasswordBuilder
    {
        return $this->setTranslatorOnBuilder(new ZodPasswordBuilder);
    }
}
