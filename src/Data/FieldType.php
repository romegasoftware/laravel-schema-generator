<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Data;

/**
 * Enum for field types
 */
enum FieldType: string
{
    case Regular = 'regular';
    case DataObject = 'data_object';
    case DataCollection = 'data_collection';
    case Array = 'array';
}
