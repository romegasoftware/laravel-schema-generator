<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Resolvers;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use Spatie\LaravelData\Resolvers\DataMorphClassResolver;
use Spatie\LaravelData\Resolvers\DataValidationRulesResolver as BaseResolver;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\Validation\DataRules;
use Spatie\LaravelData\Support\Validation\RuleDenormalizer;
use Spatie\LaravelData\Support\Validation\RuleNormalizer;
use Spatie\LaravelData\Support\Validation\ValidationPath;

use function in_array;
use function is_string;
use function str_starts_with;

class InheritingDataValidationRulesResolver extends BaseResolver
{
    public function __construct(
        DataConfig $dataConfig,
        RuleNormalizer $ruleAttributesResolver,
        RuleDenormalizer $ruleDenormalizer,
        DataMorphClassResolver $dataMorphClassResolver,
    ) {
        parent::__construct(
            $dataConfig,
            $ruleAttributesResolver,
            $ruleDenormalizer,
            $dataMorphClassResolver
        );
    }

    public function execute(
        string $class,
        array $fullPayload,
        ValidationPath $path,
        DataRules $dataRules
    ): array {
        $rules = parent::execute($class, $fullPayload, $path, $dataRules);

        $this->applyInheritedValidation($class, $fullPayload, $path, $dataRules);

        return $dataRules->rules;
    }

    protected function applyInheritedValidation(
        string $class,
        array $fullPayload,
        ValidationPath $path,
        DataRules $dataRules
    ): void {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $inheritAttributes = $parameter->getAttributes(InheritValidationFrom::class);

            if ($inheritAttributes === []) {
                continue;
            }

            $currentPropertyName = $parameter->getName();

            foreach ($inheritAttributes as $inheritAttribute) {
                $inheritInstance = $inheritAttribute->newInstance();

                if (! $inheritInstance->enforceRuntime) {
                    continue;
                }

                $sourceClass = new ReflectionClass($inheritInstance->class);
                $sourceProperty = $inheritInstance->property ?? $currentPropertyName;
                $sourceRules = $this->resolveSourceRules($sourceClass->getName(), $fullPayload);

                if (! isset($sourceRules[$sourceProperty])) {
                    continue;
                }

                $this->copyRulesToTargetProperty(
                    $sourceRules,
                    $sourceProperty,
                    $path,
                    $currentPropertyName,
                    $dataRules
                );
            }
        }
    }

    /**
     * @return array<string, array>
     */
    protected function resolveSourceRules(string $class, array $fullPayload): array
    {
        $path = ValidationPath::create();
        $rules = DataRules::create();

        parent::execute($class, $fullPayload, $path, $rules);

        return $rules->rules;
    }

    /**
     * @param  array<string, array>  $sourceRules
     */
    protected function copyRulesToTargetProperty(
        array $sourceRules,
        string $sourceProperty,
        ValidationPath $basePath,
        string $targetProperty,
        DataRules $dataRules
    ): void {
        $baseKey = $basePath->get();

        foreach ($sourceRules as $key => $rules) {
            if ($key === $sourceProperty) {
                $targetPath = $basePath->property($targetProperty);
                $this->mergeIntoDataRules($dataRules, $targetPath, $rules);

                continue;
            }

            if (! str_starts_with($key, "{$sourceProperty}.")) {
                continue;
            }

            $suffix = substr($key, strlen($sourceProperty));

            $targetKey = $targetProperty.$suffix;
            $fullKey = $baseKey ? "{$baseKey}.{$targetKey}" : $targetKey;

            $this->mergeIntoDataRules(
                $dataRules,
                ValidationPath::create($fullKey),
                $rules
            );
        }
    }

    protected function mergeIntoDataRules(
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
}
