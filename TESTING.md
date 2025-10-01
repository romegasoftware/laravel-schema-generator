# Testing Guide for Laravel Schema Generator

## Test Structure

The test suite is organized into three main categories:

```
tests/
├── Unit/           # Isolated component tests
├── Feature/        # Integration and workflow tests
├── Integration/    # End-to-end validation tests
├── Fixtures/       # Test data and mock classes
└── Traits/         # Shared test helpers
```

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/DataClassExtractorTest.php

# Run tests in parallel
php artisan test --parallel
```

## Writing Tests

### 1. Extend the Correct Base Class

Always extend our custom TestCase, not PHPUnit's:

```php
use RomegaSoftware\LaravelSchemaGenerator\Tests\TestCase;

class MyTest extends TestCase
{
    // Test methods
}
```

### 2. Use Test Traits

We provide several traits to simplify testing:

```php
class MyTest extends TestCase
{
    use InteractsWithExtractors;
    use CreatesTestClasses;

    public function test_something()
    {
        // Use trait methods
        $extractor = $this->getRequestExtractor();
        $validationSet = $this->createTestFormRequest([...]);
    }
}
```

### 3. Use Dependency Injection

Never instantiate services directly. Use the container:

```php
// ❌ Wrong
$extractor = new RequestClassExtractor();

// ✅ Correct
$extractor = $this->app->make(RequestClassExtractor::class);
```

### 4. Test Data Fixtures

Place test classes in the appropriate fixtures directory:

```php
// tests/Fixtures/FormRequests/UnifiedValidationRequest.php
#[ValidationSchema(name: 'UnifiedValidationRequestSchema')]
class UnifiedValidationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'auth_type' => ['required', 'in:password,otp'],
            'credentials.email' => ['required', 'email', 'max:255'],
            'profile.address.postal_code' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            // ... additional comprehensive rules ...
        ];
    }
}
```

## Common Test Patterns

### Testing Extractors

```php
public function test_extractor_handles_validation_rules()
{
    $extractor = $this->getRequestExtractor();
    $reflection = new ReflectionClass(UnifiedValidationRequest::class);

    $result = $extractor->extract($reflection);

    $this->assertInstanceOf(ExtractedSchemaData::class, $result);
    $this->assertEquals('UnifiedValidationRequestSchema', $result->name);
}
```

### Testing Builders

```php
public function test_builder_generates_correct_schema()
{
    $validationSet = $this->createValidationSet([
        'required' => [],
        'email' => [],
    ]);

    $schema = $this->buildZodSchema('string', $validationSet);

    $this->assertStringContainsString('z.string()', $schema);
    $this->assertStringContainsString('.email()', $schema);
}
```

### Testing with Mock Data

```php
public function test_with_mock_validator()
{
    $validator = $this->createValidator(
        ['email' => 'test@example.com'],
        ['email' => 'required|email'],
        ['email.required' => 'Email is required']
    );

    // Use validator in test
}
```

## Debugging Failed Tests

1. **Run verbose output:**

   ```bash
   vendor/bin/phpunit --verbose
   ```

2. **Use dd() for debugging:**

   ```php
   dd($result); // Will dump and die
   ```

3. **Check test logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Continuous Integration

Tests run automatically on:

- Pull requests
- Commits to main branch
- Release tags

The CI pipeline runs:

1. PHPUnit tests
2. Code coverage analysis
3. Static analysis (PHPStan)
4. Code style checks (Pint)

## Test Coverage Goals

We aim for:

- **Overall:** 80%+ coverage
- **Critical paths:** 95%+ coverage
- **New features:** 100% coverage

Check current coverage:

```bash
vendor/bin/phpunit --coverage-text
```

## Troubleshooting

### Common Issues

1. **"Class not found" errors**

   - Run `composer dump-autoload`
   - Check namespace and file location match

2. **"Target class [config] does not exist"**

   - Ensure test extends our TestCase, not PHPUnit's
   - TestCase sets up Laravel application

3. **"Too few arguments" errors**

   - Use dependency injection via container
   - Don't instantiate services directly

4. **Tests hanging or timing out**
   - Check for infinite loops in code
   - Ensure database transactions are used
   - Clear test cache: `php artisan cache:clear`

## Contributing Tests

When adding new features:

1. Write tests first (TDD approach)
2. Ensure all tests pass locally
3. Add appropriate test groups
4. Document complex test scenarios
5. Update this guide if needed
