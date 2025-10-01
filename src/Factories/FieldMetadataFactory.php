<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Factories;

use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Data\FieldMetadata;
use RomegaSoftware\LaravelSchemaGenerator\Data\FieldType;
use Spatie\LaravelData\Support\DataConfig;

/**
 * Factory for building and managing field metadata structures
 *
 * Handles the creation, organization, and querying of field metadata
 * for Data class properties and their nested relationships.
 */
class FieldMetadataFactory
{
    /** @var list<string> */
    protected array $classStack = [];

    /**
     * Build field metadata for a Data class
     */
    public function buildFieldMetadata(ReflectionClass $class, string $prefix = ''): array
    {
        $this->classStack[] = $class->getName();

        try {
            $metadata = [];
            $dataConfig = app(DataConfig::class)->getDataClass($class->getName());

            foreach ($dataConfig->properties as $property) {
                $fieldName = $property->inputMappedName ?? $property->name;
                $fullFieldName = $prefix ? $prefix.'.'.$fieldName : $fieldName;

                // Determine field type
                $fieldType = FieldType::Regular;
                $dataClass = null;

                if ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataObject) {
                    $fieldType = FieldType::DataObject;
                    $dataClass = $property->type->dataClass;
                } elseif ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataCollection) {
                    $fieldType = FieldType::DataCollection;
                    $dataClass = $property->type->dataClass;
                } elseif ($property->type->kind === \Spatie\LaravelData\Enums\DataTypeKind::DataArray) {
                    $fieldType = FieldType::DataCollection;
                    $dataClass = $property->type->dataClass;
                } elseif ($property->type->type == 'array') {
                    $fieldType = FieldType::Array;
                }

                $fieldMeta = new FieldMetadata(
                    fieldName: $fullFieldName,
                    propertyName: $property->name,
                    type: $fieldType,
                    dataClass: $dataClass,
                    mappedName: $property->inputMappedName,
                );

                // Recursively build metadata for nested Data objects
                if ($fieldType === FieldType::DataObject && $dataClass && ! $this->isClassInStack($dataClass)) {
                    $nestedClass = new ReflectionClass($dataClass);
                    $nestedMetadata = $this->buildFieldMetadata($nestedClass, $fullFieldName);
                    foreach ($nestedMetadata as $child) {
                        $fieldMeta->addChild($child);
                    }
                } elseif ($fieldType === FieldType::DataCollection && $dataClass && ! $this->isClassInStack($dataClass)) {
                    // For collections, build metadata with .* prefix
                    $nestedClass = new ReflectionClass($dataClass);
                    $nestedMetadata = $this->buildFieldMetadata($nestedClass, $fullFieldName.'.*');
                    foreach ($nestedMetadata as $child) {
                        $fieldMeta->addChild($child);
                    }
                }

                // Store metadata by both property name and mapped name (if different)
                $metadata[$property->name] = $fieldMeta;
                if ($property->inputMappedName && $property->inputMappedName !== $property->name) {
                    $metadata[$property->inputMappedName] = $fieldMeta;
                }
            }

            return $metadata;
        } finally {
            array_pop($this->classStack);
        }
    }

    /**
     * Find field metadata in parent metadata when in array context
     */
    public function findFieldMetadataInParent(string $field, array $metadata): ?FieldMetadata
    {
        // Search through all metadata entries to find a match
        foreach ($metadata as $meta) {
            if ($meta instanceof FieldMetadata) {
                // Check if this metadata has the field as a child
                $child = $meta->getChild($field);
                if ($child) {
                    return $child;
                }

                // Also check by mapped name if it exists
                if ($meta->mappedName === $field || $meta->propertyName === $field) {
                    return $meta;
                }
            }
        }

        return null;
    }

    /**
     * Flatten metadata to include all nested fields
     */
    public function flattenMetadata(array $metadata): array
    {
        $flattened = [];

        foreach ($metadata as $key => $meta) {
            if ($meta instanceof FieldMetadata) {
                $flattened[$key] = $meta;
                $flattened[$meta->fieldName] = $meta;

                // Add all children recursively
                $this->addChildrenToFlattened($meta, $flattened);
            }
        }

        return $flattened;
    }

    /**
     * Add children metadata to flattened array recursively
     */
    private function addChildrenToFlattened(FieldMetadata $parent, array &$flattened): void
    {
        foreach ($parent->children as $child) {
            $flattened[$child->propertyName] = $child;
            $flattened[$child->fieldName] = $child;

            if ($child->mappedName) {
                $flattened[$child->mappedName] = $child;
            }

            // Recursively add children
            $this->addChildrenToFlattened($child, $flattened);
        }
    }

    protected function isClassInStack(string $className): bool
    {
        return in_array($className, $this->classStack, true);
    }
}
