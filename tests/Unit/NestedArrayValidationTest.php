<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Traits\InteractsWithExtractors;

class NestedArrayValidationTest extends TestCase
{
    use InteractsWithExtractors;

    #[Test]
    public function it_groups_wildcard_rules_correctly(): void
    {
        $rules = [
            'categories' => 'array',
            'categories.*.title' => 'string|max:50',
            'tags' => 'array',
            'tags.*' => 'string|max:50',
        ];

        $extractor = $this->getRequestExtractor();
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('groupRulesByBaseField');
        $method->setAccessible(true);

        $grouped = $method->invoke($extractor, $rules);

        // Check categories grouping
        $this->assertArrayHasKey('categories', $grouped);
        $this->assertEquals('array', $grouped['categories']['rules']);
        $this->assertArrayHasKey('nested', $grouped['categories']);
        $this->assertEquals('string|max:50', $grouped['categories']['nested']['title']);

        // Check tags grouping
        $this->assertArrayHasKey('tags', $grouped);
        $this->assertEquals('array', $grouped['tags']['rules']);
        $this->assertArrayHasKey('nested', $grouped['tags']);
        $this->assertEquals('string|max:50', $grouped['tags']['nested']['*']);
    }

    #[Test]
    public function it_handles_array_without_base_rule(): void
    {
        // Test case where we only have tags.* without explicit tags: array
        $rules = [
            'tags.*' => 'string|max:50',
        ];

        $extractor = $this->getRequestExtractor();
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('groupRulesByBaseField');
        $method->setAccessible(true);

        $grouped = $method->invoke($extractor, $rules);

        // Should create a base tags field
        $this->assertArrayHasKey('tags', $grouped);
        $this->assertNull($grouped['tags']['rules']); // No base rule
        $this->assertArrayHasKey('nested', $grouped['tags']);
        $this->assertEquals('string|max:50', $grouped['tags']['nested']['*']);
    }

    #[Test]
    public function it_groups_multi_level_wildcard_rules_correctly(): void
    {
        $rules = [
            'items' => 'array',
            'items.*.variations' => 'array',
            'items.*.variations.*.type' => 'required|string',
            'items.*.variations.*.size' => 'string',
            'items.*.pricing' => 'array',
            'items.*.pricing.*.component' => 'required|in:base,tax,discount',
        ];

        $extractor = $this->getRequestExtractor();
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('groupRulesByBaseField');
        $method->setAccessible(true);

        $grouped = $method->invoke($extractor, $rules);

        // Check items base structure
        $this->assertArrayHasKey('items', $grouped);
        $this->assertEquals('array', $grouped['items']['rules']);
        $this->assertArrayHasKey('nested', $grouped['items']);

        // Check variations nested structure
        $this->assertArrayHasKey('variations', $grouped['items']['nested']);
        $this->assertEquals('array', $grouped['items']['nested']['variations']['rules']);
        $this->assertArrayHasKey('nested', $grouped['items']['nested']['variations']);

        // Check variations properties
        $this->assertArrayHasKey('type', $grouped['items']['nested']['variations']['nested']);
        $this->assertEquals('required|string', $grouped['items']['nested']['variations']['nested']['type']);
        $this->assertArrayHasKey('size', $grouped['items']['nested']['variations']['nested']);
        $this->assertEquals('string', $grouped['items']['nested']['variations']['nested']['size']);

        // Check pricing nested structure
        $this->assertArrayHasKey('pricing', $grouped['items']['nested']);
        $this->assertEquals('array', $grouped['items']['nested']['pricing']['rules']);
        $this->assertArrayHasKey('nested', $grouped['items']['nested']['pricing']);

        // Check pricing properties
        $this->assertArrayHasKey('component', $grouped['items']['nested']['pricing']['nested']);
        $this->assertEquals('required|in:base,tax,discount', $grouped['items']['nested']['pricing']['nested']['component']);
    }

    #[Test]
    public function it_handles_three_level_nesting(): void
    {
        $rules = [
            'users' => 'array',
            'users.*.profiles' => 'array',
            'users.*.profiles.*.settings' => 'array',
            'users.*.profiles.*.settings.*.key' => 'required|string',
            'users.*.profiles.*.settings.*.value' => 'required|string',
            'users.*.profiles.*.name' => 'string|max:100',
        ];

        $extractor = $this->getRequestExtractor();
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('groupRulesByBaseField');
        $method->setAccessible(true);

        $grouped = $method->invoke($extractor, $rules);

        // Check users -> profiles -> settings -> key/value structure
        $this->assertArrayHasKey('users', $grouped);
        $this->assertArrayHasKey('profiles', $grouped['users']['nested']);
        $this->assertArrayHasKey('settings', $grouped['users']['nested']['profiles']['nested']);

        // Check three-level nesting
        $settings = $grouped['users']['nested']['profiles']['nested']['settings'];
        $this->assertEquals('array', $settings['rules']);
        $this->assertArrayHasKey('key', $settings['nested']);
        $this->assertEquals('required|string', $settings['nested']['key']);
        $this->assertArrayHasKey('value', $settings['nested']);
        $this->assertEquals('required|string', $settings['nested']['value']);

        // Check that profile name is correctly nested at second level
        $this->assertArrayHasKey('name', $grouped['users']['nested']['profiles']['nested']);
        $this->assertEquals('string|max:100', $grouped['users']['nested']['profiles']['nested']['name']);
    }

    #[Test]
    public function it_handles_mixed_nesting_patterns(): void
    {
        $rules = [
            'data' => 'array',
            'data.*.simple_field' => 'string',
            'data.*.nested_array' => 'array',
            'data.*.nested_array.*' => 'integer',
            'data.*.complex.*.field' => 'required|string',
            'metadata.*.key' => 'string',
            'tags.*' => 'string',  // No base rule
        ];

        $extractor = $this->getRequestExtractor();
        $reflection = new ReflectionClass($extractor);
        $method = $reflection->getMethod('groupRulesByBaseField');
        $method->setAccessible(true);

        $grouped = $method->invoke($extractor, $rules);

        // Check data structure
        $this->assertArrayHasKey('data', $grouped);
        $this->assertEquals('array', $grouped['data']['rules']);

        // Check simple field
        $this->assertArrayHasKey('simple_field', $grouped['data']['nested']);
        $this->assertEquals('string', $grouped['data']['nested']['simple_field']);

        // Check nested array with direct items
        $this->assertArrayHasKey('nested_array', $grouped['data']['nested']);
        $this->assertEquals('array', $grouped['data']['nested']['nested_array']['rules']);
        $this->assertArrayHasKey('*', $grouped['data']['nested']['nested_array']['nested']);
        $this->assertEquals('integer', $grouped['data']['nested']['nested_array']['nested']['*']);

        // Check complex multi-level nesting
        $this->assertArrayHasKey('complex', $grouped['data']['nested']);
        $this->assertArrayHasKey('field', $grouped['data']['nested']['complex']['nested']);
        $this->assertEquals('required|string', $grouped['data']['nested']['complex']['nested']['field']);

        // Check metadata and tags
        $this->assertArrayHasKey('metadata', $grouped);
        $this->assertArrayHasKey('tags', $grouped);
        $this->assertNull($grouped['tags']['rules']); // No base rule for tags
    }
}
