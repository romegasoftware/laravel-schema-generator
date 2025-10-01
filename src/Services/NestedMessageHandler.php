<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Services;

use Illuminate\Validation\Validator;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use Spatie\LaravelData\Support\DataConfig;

/**
 * Service for handling nested validation messages in Data classes
 *
 * Manages the collection, merging, and inheritance of validation messages
 * across nested Data structures and inherited validation rules.
 */
class NestedMessageHandler
{
    /** @var array<string, bool> */
    protected array $messageCollectionStack = [];

    public function __construct(protected ?MessageResolutionService $messageService = new MessageResolutionService) {}

    /**
     * Collect all custom messages from a Data class and its nested structures
     */
    public function collectMessages(ReflectionClass $class, string $prefix = ''): array
    {
        $className = $class->getName();

        if ($this->messageCollectionStack[$className] ?? false) {
            return [];
        }

        $this->messageCollectionStack[$className] = true;

        $messages = [];

        try {
            // Get messages from the current class
            $messages = array_merge($messages, $this->getClassMessages($class, $prefix));

            // Get messages from nested Data objects
            $messages = array_merge($messages, $this->collectNestedMessages($class, $prefix));
        } finally {
            unset($this->messageCollectionStack[$className]);
        }

        return $messages;
    }

    /**
     * Get messages directly from a class
     */
    protected function getClassMessages(ReflectionClass $class, string $prefix): array
    {
        $messages = [];

        if (method_exists($class->getName(), 'messages')) {
            $classMessages = $class->getName()::messages();
            foreach ($classMessages as $key => $message) {
                $fullKey = $prefix ? $prefix.'.'.$key : $key;
                $messages[$fullKey] = $message;
            }
        }

        return $messages;
    }

    /**
     * Collect messages from nested Data objects
     */
    protected function collectNestedMessages(ReflectionClass $class, string $prefix): array
    {
        $messages = [];
        $dataConfig = app(DataConfig::class)->getDataClass($class->getName());

        foreach ($dataConfig->properties as $property) {
            $fieldName = $property->inputMappedName ?? $property->name;

            if ($this->isNestedDataObject($property)) {
                if ($this->messageCollectionStack[$property->type->dataClass] ?? false) {
                    continue;
                }

                $messages = array_merge(
                    $messages,
                    $this->processNestedDataObject($property, $fieldName, $prefix)
                );
            } elseif ($this->isDataCollection($property)) {
                if ($this->messageCollectionStack[$property->type->dataClass] ?? false) {
                    continue;
                }

                $messages = array_merge(
                    $messages,
                    $this->processDataCollection($property, $fieldName, $prefix)
                );
            }
        }

        return $messages;
    }

    /**
     * Process nested Data object for messages
     */
    protected function processNestedDataObject($property, string $fieldName, string $prefix): array
    {
        $nestedClass = new ReflectionClass($property->type->dataClass);

        // Determine the nested prefix based on context
        $nestedPrefix = $this->buildNestedPrefix($prefix, $fieldName);

        return $this->collectMessages($nestedClass, $nestedPrefix);
    }

    /**
     * Process Data collection for messages
     */
    protected function processDataCollection($property, string $fieldName, string $prefix): array
    {
        $nestedClass = new ReflectionClass($property->type->dataClass);
        $nestedPrefix = $prefix ? $prefix.'.'.$fieldName.'.*' : $fieldName.'.*';

        return $this->collectMessages($nestedClass, $nestedPrefix);
    }

    /**
     * Build the nested prefix for message keys
     */
    protected function buildNestedPrefix(string $prefix, string $fieldName): string
    {
        // If we're already in an array context, nested objects get an extra .*
        if (str_contains($prefix, '.*')) {
            return $prefix.'.'.$fieldName.'.*';
        }

        return $prefix ? $prefix.'.'.$fieldName : $fieldName;
    }

    /**
     * Check if property is a nested Data object
     */
    protected function isNestedDataObject($property): bool
    {
        return $property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataObject
            && $property->type->dataClass;
    }

    /**
     * Check if property is a Data collection
     */
    protected function isDataCollection($property): bool
    {
        return ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataCollection ||
                $property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataArray)
            && $property->type->dataClass;
    }

    /**
     * Merge inherited validation messages from InheritValidationFrom attributes
     */
    public function mergeInheritedMessages(ReflectionClass $class, Validator $validator): void
    {
        $constructor = $class->getConstructor();
        if (! $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $this->processInheritedParameter($parameter, $validator);
        }
    }

    /**
     * Process a single parameter for inherited messages
     */
    protected function processInheritedParameter($parameter, Validator $validator): void
    {
        $inheritAttributes = $parameter->getAttributes(InheritValidationFrom::class);

        if (empty($inheritAttributes)) {
            return;
        }

        foreach ($inheritAttributes as $inheritAttribute) {
            $this->mergeMessagesFromAttribute($inheritAttribute, $parameter, $validator);
        }
    }

    /**
     * Merge messages from an InheritValidationFrom attribute
     */
    protected function mergeMessagesFromAttribute($inheritAttribute, $parameter, Validator $validator): void
    {
        $inheritInstance = $inheritAttribute->newInstance();
        $sourceClass = $inheritInstance->class;
        $sourceProperty = $inheritInstance->property ?? $parameter->getName();

        if (! method_exists($sourceClass, 'messages')) {
            return;
        }

        $sourceMessages = $sourceClass::messages();
        $currentPropertyName = $parameter->getName();

        foreach ($sourceMessages as $key => $message) {
            if ($this->shouldInheritMessage($key, $sourceProperty)) {
                $newKey = $this->buildInheritedMessageKey($key, $sourceProperty, $currentPropertyName);

                if (! isset($validator->customMessages[$newKey])) {
                    $validator->customMessages[$newKey] = $message;
                }
            }
        }
    }

    /**
     * Check if a message should be inherited
     */
    protected function shouldInheritMessage(string $key, string $sourceProperty): bool
    {
        return str_starts_with($key, $sourceProperty.'.');
    }

    /**
     * Build the key for an inherited message
     */
    protected function buildInheritedMessageKey(string $key, string $sourceProperty, string $currentProperty): string
    {
        $ruleType = substr($key, strlen($sourceProperty) + 1);

        return $currentProperty.'.'.$ruleType;
    }

    /**
     * Apply all collected messages to a validator
     */
    public function applyMessages(Validator $validator, array $messages): void
    {
        $this->messageService->mergeNestedMessages($messages, $validator);
    }
}
