<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Traits;

use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidation;
use RomegaSoftware\LaravelSchemaGenerator\Data\ResolvedValidationSet;
use RomegaSoftware\LaravelSchemaGenerator\Factories\ZodBuilderFactory;

trait InteractsWithBuilders
{
    protected function getBuilderFactory(): ZodBuilderFactory
    {
        return $this->app->make(ZodBuilderFactory::class);
    }

    protected function createResolvedValidation(string $rule, array $parameters = []): ResolvedValidation
    {
        return new ResolvedValidation($rule, $parameters);
    }

    protected function createValidationSet(array $validations = [], array $customMessages = []): ResolvedValidationSet
    {
        $set = new ResolvedValidationSet;

        foreach ($validations as $rule => $params) {
            if (is_numeric($rule)) {
                $set->addValidation(new ResolvedValidation($params, []));
            } else {
                $set->addValidation(new ResolvedValidation($rule, (array) $params));
            }
        }

        foreach ($customMessages as $rule => $message) {
            $set->addCustomMessage($rule, $message);
        }

        return $set;
    }

    protected function buildZodSchema(string $type, ResolvedValidationSet $validations): string
    {
        $factory = $this->getBuilderFactory();
        $builder = $factory->createBuilder($type, $validations);

        return $builder->build();
    }
}
