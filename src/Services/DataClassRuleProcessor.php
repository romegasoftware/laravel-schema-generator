<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Support\Arr;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaAnnotatedRule;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaNameGenerator;
use RomegaSoftware\LaravelSchemaGenerator\Traits\Makeable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Present;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\DataRules;
use Spatie\LaravelData\Support\Validation\PropertyRules;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\LaravelData\Support\Validation\ValidationPath;

use function in_array;
use function is_string;

/**
 * Service for processing Data class validation rules
 *
 * Handles the complex logic of extracting and processing validation rules
 * from Spatie Laravel Data classes, including nested objects and collections.
 */
class DataClassRuleProcessor
{
    use Makeable;

    /** @var array<string, array> */
    protected array $cachedRules = [];

    /** @var array<string, bool> */
    protected array $processingClasses = [];

    /** @var array<string, array<string, SchemaFragment>> */
    /**
     * @var array<string, array<string, list<SchemaFragment>>>
     */
    protected array $schemaOverridesByClass = [];

    public function __construct(
        protected DataConfig $dataConfig,
        protected RuleDenormalizer $ruleDenormalizer
    ) {}

    /**
     * Process all properties of a Data class and extract validation rules
     */
    public function processDataClass(ReflectionClass $class): array
    {
        $className = $class->getName();

        if (isset($this->cachedRules[$className]) && ! ($this->processingClasses[$className] ?? false)) {
            return $this->cachedRules[$className];
        }

        if ($this->processingClasses[$className] ?? false) {
            return $this->cachedRules[$className] ?? [];
        }

        $this->processingClasses[$className] = true;
        $this->schemaOverridesByClass[$className] = [];

        $dataClass = $this->dataConfig->getDataClass($class->getName());
        $dataRules = DataRules::create();
        $fullPayload = [];
        $path = ValidationPath::create();

        // Expose a reference for recursively nested Data classes
        $this->cachedRules[$className] = &$dataRules->rules;

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

        $rules = $dataRules->rules;

        // Replace reference with the finalized rule set
        unset($this->cachedRules[$className]);
        $this->cachedRules[$className] = $rules;
        unset($this->processingClasses[$className]);

        return $rules;
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
        /** @var array<string, mixed> $overwrittenRules */
        $overwrittenRules = app()->call($class->getName().'::rules', ['context' => $validationContext]);

        $shouldMergeRules = $dataClass->attributes->has(\Spatie\LaravelData\Attributes\MergeValidationRules::class);

        foreach ($overwrittenRules as $key => $rules) {
            $this->recordSchemaOverride($class->getName(), $key, $rules);

            $rules = collect(Arr::wrap($rules))
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

            $currentPropertyName = $parameter->getName();

            foreach ($inheritAttributes as $inheritAttribute) {
                $inheritInstance = $inheritAttribute->newInstance();
                $sourceClass = new ReflectionClass($inheritInstance->class);
                $sourceProperty = $inheritInstance->property ?? $currentPropertyName;

                // Recursively extract rules from the source class
                $sourceRules = $this->processDataClass($sourceClass);

                // Find the source property rules
                if (isset($sourceRules[$sourceProperty])) {
                    $propertyPath = $path->property($currentPropertyName);

                    $this->mergeRulesIntoDataRules(
                        $dataRules,
                        $propertyPath,
                        $sourceRules[$sourceProperty]
                    );

                    $inheritedFragments = $this->schemaOverridesByClass[$inheritInstance->class][$sourceProperty] ?? [];

                    foreach ($inheritedFragments as $fragment) {
                        $this->addSchemaFragment($class->getName(), $currentPropertyName, $fragment);
                    }
                }

                $collectionAttributes = [];
                if ($class->hasProperty($currentPropertyName)) {
                    $propertyReflection = $class->getProperty($currentPropertyName);
                    $collectionAttributes = $propertyReflection->getAttributes(DataCollectionOf::class);
                }

                if (
                    ! empty($collectionAttributes) &&
                    $this->needsArrayBaseFragment($class->getName(), $currentPropertyName)
                ) {
                    $collectionInstance = $collectionAttributes[0]->newInstance();
                    $schemaName = SchemaNameGenerator::fromClass(new ReflectionClass($collectionInstance->class));
                    $this->addSchemaFragment(
                        $class->getName(),
                        $currentPropertyName,
                        SchemaFragment::literal("z.array({$schemaName})")
                    );
                }
            }
        }
    }

    /**
     * Merge a new set of rules into the DataRules collection for a given path.
     */
    protected function mergeRulesIntoDataRules(
        DataRules $dataRules,
        ValidationPath $path,
        array $rules
    ): void {
        $key = $path->get();

        if ($key === null) {
            return;
        }

        $existing = $dataRules->rules[$key] ?? [];
        $merged = $this->mergeRuleSet($existing, $rules);

        $dataRules->add($path, $merged);
    }

    /**
     * Merge two rule arrays, avoiding duplicate string rules.
     */
    protected function mergeRuleSet(array $existing, array $incoming): array
    {
        foreach ($incoming as $rule) {
            if (is_string($rule)) {
                if (! in_array($rule, $existing, true)) {
                    $existing[] = $rule;
                }

                continue;
            }

            $existing[] = $rule;
        }

        return $existing;
    }

    public function getSchemaOverridesForClass(string $className): array
    {
        return $this->schemaOverridesByClass[$className] ?? [];
    }

    protected function recordSchemaOverride(string $className, string $field, mixed $rules): void
    {
        $fragment = $this->findSchemaFragment($rules);

        if ($fragment !== null) {
            $this->addSchemaFragment($className, $field, $fragment);
        }
    }

    protected function addSchemaFragment(string $className, string $field, SchemaFragment $fragment): void
    {
        $bucket = $this->schemaOverridesByClass[$className][$field] ?? [];

        foreach ($bucket as $existing) {
            if ($existing->code() === $fragment->code()) {
                return;
            }
        }

        $bucket[] = $fragment;

        $this->schemaOverridesByClass[$className][$field] = $bucket;
    }

    protected function findSchemaFragment(mixed $rules): ?SchemaFragment
    {
        if ($rules instanceof SchemaAnnotatedRule) {
            return $rules->schemaFragment();
        }

        if (is_array($rules) || $rules instanceof \Traversable) {
            foreach ($rules as $rule) {
                $fragment = $this->findSchemaFragment($rule);

                if ($fragment !== null) {
                    return $fragment;
                }
            }
        }

        return null;
    }

    protected function needsArrayBaseFragment(string $className, string $field): bool
    {
        $bucket = $this->schemaOverridesByClass[$className][$field] ?? [];

        if (empty($bucket)) {
            return false;
        }

        foreach ($bucket as $fragment) {
            if ($fragment->replaces()) {
                return false;
            }
        }

        return true;
    }
}
