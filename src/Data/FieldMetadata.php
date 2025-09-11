<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

/**
 * Metadata about a field to track its type through the extraction pipeline
 */
class FieldMetadata
{
    public function __construct(
        /** The field name (may include prefix like songs.*) */
        public readonly string $fieldName,

        /** The original property name without prefix */
        public readonly string $propertyName,

        /** The type of field */
        public readonly FieldType $type,

        /** For Data objects and DataCollections, the class name */
        public readonly ?string $dataClass = null,

        /** The mapped input name if different from property name */
        public readonly ?string $mappedName = null,

        /** Child field metadata for nested structures */
        public array $children = [],
    ) {
    }

    /**
     * Check if this field is a nested Data object (not a collection)
     */
    public function isNestedDataObject(): bool
    {
        return $this->type === FieldType::DataObject && $this->dataClass !== null;
    }

    /**
     * Check if this field is a Data collection/array
     */
    public function isDataCollection(): bool
    {
        return $this->type === FieldType::DataCollection && $this->dataClass !== null;
    }

    /**
     * Add child metadata
     */
    public function addChild(FieldMetadata $child): void
    {
        $this->children[$child->propertyName] = $child;
    }

    /**
     * Get child metadata by property name
     */
    public function getChild(string $propertyName): ?FieldMetadata
    {
        return $this->children[$propertyName] ?? null;
    }

    /**
     * Check if this field has children (nested properties)
     */
    public function hasChildren(): bool
    {
        return !empty($this->children);
    }
}
