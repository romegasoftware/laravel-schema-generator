---
sidebar_position: 2.5
---

# Custom Validation Rules

Laravel’s validation system lets you express complex business rules using dedicated rule classes or inline closures. The schema generator understands those same patterns, so you can keep the PHP logic exactly as documented and still describe the TypeScript shape that Zod should emit.

## Rule Objects with Schema Metadata

Add the `SchemaAnnotatedRule` contract to any rule class and pull in the `InteractsWithSchemaFragment` trait to attach a literal Zod fragment right beside the validation logic.

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use RomegaSoftware\LaravelSchemaGenerator\Concerns\InteractsWithSchemaFragment;
use RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaAnnotatedRule;

class Uppercase implements ValidationRule, SchemaAnnotatedRule
{
    use InteractsWithSchemaFragment;

    public function __construct()
    {
        $this->literal(<<<'ZOD'
        z.string().refine(value => value === value.toUpperCase(), {
            message: 'The value must be uppercase.',
        })
        ZOD);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strtoupper((string) $value) !== $value) {
            $fail('validation.uppercase')->translate();
        }
    }
}
```

Use the rule anywhere you would inside FormRequests, Spatie Data classes, or manual validators, and the generator reuses the stored literal:

```php
use App\Rules\Uppercase;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;

#[ValidationSchema]
class DisplayNameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new Uppercase],
        ];
    }
}
```

Need more context? Implement Laravel’s core interfaces such as `DataAwareRule`, `ValidatorAwareRule`, or the implicit rule variants on the same class—they coexist happily with `SchemaAnnotatedRule`.

## Inline Closures with `SchemaRule`

For one-off inline rules, start from `SchemaRule::make()` and chain the Zod fragment you want to append or replace. The helper implements both `ValidationRule` and `InvokableRule`, so it works across Laravel 12.x validators and legacy entry points.

```php
use Closure;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaRule;

return [
    'items' => [
        'required',
        'array',
        SchemaRule::make(
            static function (string $attribute, mixed $value, Closure $fail, string $message): void {
                if (collect($value)->sum('qty') < 12) {
                    $fail($message);
                }
            }
        )
            ->append(static function (string $encodedMessage): string {
                return <<<ZOD
                    .superRefine((items, ctx) => {
                        const total = items.reduce((sum, item) => sum + item.qty, 0);
                        if (total < 12) {
                            ctx.addIssue({
                                code: 'custom',
                                message: {$encodedMessage},
                                path: ['items'],
                            });
                        }
                    })
                    ZOD;
            })
            ->failWith('You must order at least 12 total units.'),
    ],
];
```

Prefix the fragment with `.` to append behaviour to the inferred Zod builder. The callable you pass to `append()` receives the JSON-encoded message as the first argument (and the raw string as the second argument if you declare it). Omit the dot to supply a complete replacement when you need to take full control.

```php
SchemaRule::make(
    static fn ($attribute, $value, $fail, string $message) => /* ... */
)->replace(static function (string $encodedMessage, ?string $rawMessage = null): string {
    return <<<ZOD
        z.object({
            subtotal: z.number(),
            tax: z.number(),
        })
        ZOD;
});
```

In this example the literal is used as-is because it does not start with a period.

### Rule Objects with the Trait

If you prefer dedicated rule classes, the `InteractsWithSchemaFragment` trait now exposes the same fluent helpers:

```php
final class TotalOrderItemsRule implements ValidationRule, SchemaAnnotatedRule
{
    use InteractsWithSchemaFragment;

    public function __construct()
    {
        $this->withFailureMessage('You must order at least 12 total units.')
            ->append(static function (string $encodedMessage): string {
                return <<<ZOD
                    .superRefine((items, ctx) => {
                        const total = items.reduce((sum, item) => sum + item.qty, 0);
                        if (total < 12) {
                            ctx.addIssue({
                                code: 'custom',
                                message: {$encodedMessage},
                                path: ['items'],
                            });
                        }
                    })
                    ZOD;
            });
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (collect($value)->sum('qty') < 12) {
            $fail($this->failureMessage() ?? 'You must order at least 12 total units.');
        }
    }
}
```

## API Reference

Most projects only need the helpers shown above, but the underlying pieces are available if you want more control:

- `RomegaSoftware\LaravelSchemaGenerator\Contracts\SchemaAnnotatedRule` — marker interface the generator looks for on rule objects.
- `RomegaSoftware\LaravelSchemaGenerator\Concerns\InteractsWithSchemaFragment` — adds:
  - `literal(string $typescript)` (preferred) and `withSchemaFragment(SchemaFragment $fragment)`
  - `append(string|callable $typescript, ?string $message = null)` and `replace(string|callable $typescript, ?string $message = null)` for rule objects
  - `schemaFragment()` / `hasSchemaFragment()` accessors for advanced scenarios
- `RomegaSoftware\LaravelSchemaGenerator\Support\SchemaRule` — helper exposing `make()`, `append()`, `replace()`, and `failWith()` fluent helpers for inline callbacks.
- `RomegaSoftware\LaravelSchemaGenerator\Data\SchemaFragment` — small value object if you prefer to build fragments programmatically. Use `replaces()` / `appends()` to see how a fragment will be applied.

Stick with `literal()` unless you have a good reason to reach for the lower-level methods.

## Translating and Localising Messages

Because you’re still calling Laravel’s `$fail()` callback, you can continue to pass translation keys or call `->translate()` just like the framework examples. The literal snippet is purely for the generated TypeScript—messages and localisation keep working on the PHP side.

## When to Use Overrides

Fallback to custom fragments only when the built-in rule coverage doesn’t express the TypeScript shape you need. Most Laravel rules already map cleanly to Zod. The helpers above are meant for bespoke logic like aggregate checks, cross-field comparisons, or third-party constraints that the default generator can’t infer.
