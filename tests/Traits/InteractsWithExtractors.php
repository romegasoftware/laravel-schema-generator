<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Traits;

use RomegaSoftware\LaravelSchemaGenerator\Extractors\DataClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Extractors\RequestClassExtractor;
use RomegaSoftware\LaravelSchemaGenerator\Services\LaravelValidationResolver;
use Spatie\LaravelData\Resolvers\DataValidatorResolver;

trait InteractsWithExtractors
{
    protected function getRequestExtractor(): RequestClassExtractor
    {
        return $this->app->make(RequestClassExtractor::class);
    }
    
    protected function getDataExtractor(): DataClassExtractor
    {
        return $this->app->make(DataClassExtractor::class);
    }
    
    protected function createRequestExtractorWithMocks(?LaravelValidationResolver $resolver = null): RequestClassExtractor
    {
        return new RequestClassExtractor(
            $resolver ?? $this->app->make(LaravelValidationResolver::class)
        );
    }
    
    protected function createDataExtractorWithMocks(
        ?LaravelValidationResolver $resolver = null,
        ?DataValidatorResolver $dataValidator = null
    ): DataClassExtractor {
        return new DataClassExtractor(
            $resolver ?? $this->app->make(LaravelValidationResolver::class),
            $dataValidator ?? $this->app->make(DataValidatorResolver::class)
        );
    }
}