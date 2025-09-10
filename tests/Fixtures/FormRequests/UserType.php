<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests;

enum UserType: string
{
    case super_admin = 'Super Admin';
    case basic_user = 'Basic User';
}
