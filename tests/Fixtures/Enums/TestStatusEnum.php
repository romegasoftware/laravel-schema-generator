<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\Enums;

enum TestStatusEnum: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';
}
