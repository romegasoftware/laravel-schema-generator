<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Resolvers;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use Spatie\LaravelData\Resolvers\DataValidationMessagesAndAttributesResolver as BaseResolver;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\Validation\ValidationPath;

use function app;
use function array_key_exists;
use function str_starts_with;

class InheritingDataValidationMessagesAndAttributesResolver extends BaseResolver
{
    public function __construct(DataConfig $dataConfig)
    {
        parent::__construct($dataConfig);
    }

    public function execute(
        string $class,
        array $fullPayload,
        ValidationPath $path,
        array $nestingChain = [],
    ): array {
        $result = parent::execute($class, $fullPayload, $path, $nestingChain);

        $messages = $result['messages'];
        $attributes = $result['attributes'];

        $this->applyInheritedMessages($class, $path, $messages);

        return [
            'messages' => $messages,
            'attributes' => $attributes,
        ];
    }

    protected function applyInheritedMessages(
        string $class,
        ValidationPath $path,
        array &$messages
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

                $sourceClass = $inheritInstance->class;
                if (! method_exists($sourceClass, 'messages')) {
                    continue;
                }

                $sourceMessages = app()->call([$sourceClass, 'messages']);

                $sourceProperty = $inheritInstance->property ?? $currentPropertyName;
                $this->mergeMessagesForProperty(
                    $path,
                    $currentPropertyName,
                    $sourceProperty,
                    $sourceMessages,
                    $messages
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sourceMessages
     * @param  array<string, mixed>  $messages
     */
    protected function mergeMessagesForProperty(
        ValidationPath $path,
        string $targetProperty,
        string $sourceProperty,
        array $sourceMessages,
        array &$messages
    ): void {
        foreach ($sourceMessages as $key => $message) {
            $messageKey = (string) $key;

            if (! str_starts_with($messageKey, "{$sourceProperty}.")) {
                continue;
            }

            $ruleType = substr($messageKey, strlen($sourceProperty) + 1);
            $targetKey = $path->property("{$targetProperty}.{$ruleType}")->get();

            if (! array_key_exists($targetKey, $messages)) {
                $messages[$targetKey] = $message;
            }
        }
    }
}
