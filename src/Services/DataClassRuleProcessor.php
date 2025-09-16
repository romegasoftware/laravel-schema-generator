<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\DataRules;
use Spatie\LaravelData\Support\Validation\PropertyRules;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;

/**
 * Service for processing Data class validation rules
 *
 * Handles the complex logic of extracting and processing validation rules
 * from Spatie Laravel Data classes, including nested objects and collections.
 */
class DataClassRuleProcessor
{
    use Makeable;

    public function __construct(
        protected DataConfig $dataConfig,
        protected RuleDenormalizer $ruleDenormalizer
    ) {}

    /**
     * Process all properties of a Data class and extract validation rules
     */
    public function processDataClass(ReflectionClass $class): array
    {
        $dataClass = $this->dataConfig->getDataClass($class->getName());
        $dataRules = DataRules::create();
        $fullPayload = [];
        $path = ValidationPath::create();

        foreach ($dataClass->properties as $dataProperty) {
            if ($dataProperty->validate === false) {
                continue;
            }

            $this->processProperty($dataProperty, $dataRules, $path, $fullPayload);
        }

        // Handle custom rules method
        $this->processCustomRulesMethod($class, $dataClass, $dataRules, $path, $fullPayload);

        // Handle inherited validation
        $this->processInheritedValidation($class, $dataRules, $path);

        return $dataRules->rules;
    }

    /**
     * Process a single property and add its rules
     */
    protected function processProperty(
        DataProperty $dataProperty,
        DataRules $dataRules,
        ValidationPath $path,
        array $fullPayload
    ): void {
        $propertyPath = $path->property($dataProperty->inputMappedName ?? $dataProperty->name);

        if ($dataProperty->type->kind->isDataObject()) {
            $this->processDataObjectProperty($dataProperty, $dataRules, $propertyPath, $path, $fullPayload);
        } elseif ($dataProperty->type->kind->isDataCollectable()) {
            $this->processDataCollectionProperty($dataProperty, $dataRules, $propertyPath, $path, $fullPayload);
        } else {
            $this->processRegularProperty($dataProperty, $dataRules, $propertyPath, $path, $fullPayload);
        }
    }

    /**
     * Process a Data object property
     */
    protected function processDataObjectProperty(
        DataProperty $dataProperty,
        DataRules $dataRules,
        ValidationPath $propertyPath,
        ValidationPath $path,
        array $fullPayload
    ): void {
        $propertyRules = PropertyRules::create();
        $context = new ValidationContext($fullPayload, $fullPayload, $path);

        // Apply rule inferrers
        $this->applyRuleInferrers($dataProperty, $propertyRules, $context);

        // Add the rules for this property
        $rules = $this->ruleDenormalizer->execute(
            $propertyRules->all(),
            $propertyPath
        );
        $dataRules->add($propertyPath, $rules);

        // Recursively extract rules for the nested data object
        if ($dataProperty->type->dataClass) {
            $nestedClass = new ReflectionClass($dataProperty->type->dataClass);
            $nestedRules = $this->processDataClass($nestedClass);

            // Add nested rules with proper path
            foreach ($nestedRules as $nestedKey => $nestedRule) {
                $fullPath = $propertyPath->property($nestedKey);
                $dataRules->add($fullPath, $nestedRule);
            }
        }
    }

    /**
     * Process a Data collection property
     */
    protected function processDataCollectionProperty(
        DataProperty $dataProperty,
        DataRules $dataRules,
        ValidationPath $propertyPath,
        ValidationPath $path,
        array $fullPayload
    ): void {
        $propertyRules = PropertyRules::create();
        $propertyRules->add(new Present);
        $propertyRules->add(new ArrayType);

        $context = new ValidationContext($fullPayload, $fullPayload, $path);

        // Apply rule inferrers
        $this->applyRuleInferrers($dataProperty, $propertyRules, $context);

        // Add the rules for this property
        $rules = $this->ruleDenormalizer->execute(
            $propertyRules->all(),
            $propertyPath
        );
        $dataRules->add($propertyPath, $rules);

        // If it's a collection of Data objects, add nested validation
        if ($dataProperty->type->dataClass) {
            $nestedClass = new ReflectionClass($dataProperty->type->dataClass);
            $nestedRules = $this->processDataClass($nestedClass);

            // Add nested rules for array items
            foreach ($nestedRules as $nestedKey => $nestedRule) {
                $fullPath = ValidationPath::create($propertyPath->get().'.*.'.$nestedKey);
                $dataRules->add($fullPath, $nestedRule);
            }
        }
    }

    /**
     * Process a regular property
     */
    protected function processRegularProperty(
        DataProperty $dataProperty,
        DataRules $dataRules,
        ValidationPath $propertyPath,
        ValidationPath $path,
        array $fullPayload
    ): void {
        $propertyRules = PropertyRules::create();
        $context = new ValidationContext($fullPayload, $fullPayload, $path);

        // Apply all rule inferrers to build the complete rule set
        $this->applyRuleInferrers($dataProperty, $propertyRules, $context);

        // Denormalize the rules to the format expected by Laravel validator
        $rules = $this->ruleDenormalizer->execute(
            $propertyRules->all(),
            $propertyPath
        );

        $dataRules->add($propertyPath, $rules);
    }

    /**
     * Apply rule inferrers to a property
     */
    protected function applyRuleInferrers(
        DataProperty $dataProperty,
        PropertyRules $propertyRules,
        ValidationContext $context
    ): void {
        foreach ($this->dataConfig->ruleInferrers as $inferrer) {
            $inferrer->handle($dataProperty, $propertyRules, $context);
        }
    }

    /**
     * Process custom rules() method if it exists
     */
    protected function processCustomRulesMethod(
        ReflectionClass $class,
        $dataClass,
        DataRules $dataRules,
        ValidationPath $path,
        array $fullPayload
    ): void {
        if (! method_exists($class->getName(), 'rules')) {
            return;
        }

        $validationContext = new ValidationContext($fullPayload, $fullPayload, $path);
        $overwrittenRules = app()->call([$class->getName(), 'rules'], ['context' => $validationContext]);

        $shouldMergeRules = $dataClass->attributes->has(\Spatie\LaravelData\Attributes\MergeValidationRules::class);

        foreach ($overwrittenRules as $key => $rules) {
            $rules = collect(\Illuminate\Support\Arr::wrap($rules))
                ->map(fn (mixed $rule) => $this->ruleDenormalizer->execute($rule, $path))
                ->flatten()
                ->all();

            $shouldMergeRules
                ? $dataRules->merge($path->property($key), $rules)
                : $dataRules->add($path->property($key), $rules);
        }
    }

    /**
     * Process InheritValidationFrom attributes
     */
    protected function processInheritedValidation(
        ReflectionClass $class,
        DataRules $dataRules,
        ValidationPath $path
    ): void {
        $constructor = $class->getConstructor();
        if (! $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $inheritAttributes = $parameter->getAttributes(InheritValidationFrom::class);

            if (empty($inheritAttributes)) {
                continue;
            }

            foreach ($inheritAttributes as $inheritAttribute) {
                $inheritInstance = $inheritAttribute->newInstance();
                $sourceClass = new ReflectionClass($inheritInstance->class);
                $sourceProperty = $inheritInstance->property ?? $parameter->getName();

                // Recursively extract rules from the source class
                $sourceRules = $this->processDataClass($sourceClass);

                // Find the source property rules
                if (isset($sourceRules[$sourceProperty])) {
                    // Override the current property's rules with inherited ones
                    $currentPropertyName = $parameter->getName();
                    $dataRules->add(
                        $path->property($currentPropertyName),
                        $sourceRules[$sourceProperty]
                    );
                }
            }
        }
    }
}
