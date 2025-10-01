<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\FormRequests;

use Illuminate\Foundation\Http\FormRequest;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema(name: 'DeploymentOptionsSchema')]
class DeploymentOptionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'options.gitignore' => ['nullable', 'string', 'max:255'],
            'options.workflow' => ['nullable', 'string', 'max:255'],
            'options.plugin_url' => ['nullable', 'url'],
            'options.repository.name' => ['nullable', 'string', 'max:255'],
            'options.repository.description' => ['nullable', 'string', 'max:255'],
            'options.repository.branch' => ['nullable', 'string', 'max:255'],
            'options.sftp_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
