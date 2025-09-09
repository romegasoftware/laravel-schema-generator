---
sidebar_position: 4
---

# TypeScript Integration

Learn how to integrate Laravel Zod Generator with existing TypeScript workflows, build tools, and development processes for a seamless development experience.

## TypeScript Transformer Integration

Laravel Zod Generator seamlessly integrates with Spatie's TypeScript Transformer for coordinated type and validation generation.

### Automatic Integration

When both packages are installed, Zod schemas are automatically generated after TypeScript types:

```bash
composer require spatie/laravel-typescript-transformer
php artisan typescript:transform

# Output:
✓ Generated TypeScript types at resources/js/types/generated.d.ts
✓ Generated Zod schemas at resources/js/types/zod-schemas.ts (automatic hook)
```

### Configuration

Control the integration behavior:

```php
// config/laravel-schema-generator.php
'features' => [
    // Auto-run after typescript:transform command
    'typescript_transformer_hook' => 'auto', // 'auto', true, or false
],
```

### Manual Control

Disable automatic integration and run commands separately:

```php
'features' => [
    'typescript_transformer_hook' => false,
],
```

```bash
php artisan typescript:transform
php artisan schema:generate
```

## Build Tool Integration

### Vite Integration

Add schema generation to your Vite build process:

#### vite.config.ts

```typescript
import { defineConfig } from "vite";
import { exec } from "child_process";
import { promisify } from "util";

const execAsync = promisify(exec);

export default defineConfig({
  plugins: [
    {
      name: "laravel-schema-generator",
      buildStart: async () => {
        if (process.env.NODE_ENV === "development") {
          console.log("Generating Zod schemas...");
          try {
            await execAsync("php artisan schema:generate");
            console.log("✓ Zod schemas generated");
          } catch (error) {
            console.error("Failed to generate Zod schemas:", error);
          }
        }
      },
    },
  ],
  // ... other config
});
```

#### package.json Scripts

```json
{
  "scripts": {
    "dev": "php artisan schema:generate && vite",
    "build": "php artisan schema:generate && vite build",
    "preview": "vite preview",
    "generate-types": "php artisan typescript:transform && php artisan schema:generate"
  }
}
```

### Webpack Integration

For projects using Laravel Mix or custom Webpack:

#### webpack.mix.js

```javascript
const mix = require("laravel-mix");
const { exec } = require("child_process");

// Generate schemas before compilation
mix.before(() => {
  return new Promise((resolve, reject) => {
    exec("php artisan schema:generate", (error, stdout, stderr) => {
      if (error) {
        console.error("Schema generation failed:", error);
        reject(error);
      } else {
        console.log("✓ Zod schemas generated");
        resolve();
      }
    });
  });
});

mix.ts("resources/js/app.ts", "public/js").version();
```

### Rollup Integration

#### rollup.config.js

```javascript
import { exec } from "child_process";
import { promisify } from "util";

const execAsync = promisify(exec);

const zodGeneratorPlugin = () => ({
  name: "zod-generator",
  buildStart: async () => {
    console.log("Generating Zod schemas...");
    try {
      await execAsync("php artisan schema:generate");
      console.log("✓ Zod schemas generated");
    } catch (error) {
      console.error("Schema generation failed:", error);
      throw error;
    }
  },
});

export default {
  plugins: [
    zodGeneratorPlugin(),
    // ... other plugins
  ],
  // ... other config
};
```

## CI/CD Pipeline Integration

### GitHub Actions

#### .github/workflows/ci.yml

```yaml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring, dom, fileinfo

      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: 18

      - name: Install PHP dependencies
        run: composer install --prefer-dist --no-progress

      - name: Install Node dependencies
        run: npm ci

      - name: Generate TypeScript types and Zod schemas
        run: |
          php artisan typescript:transform
          php artisan schema:generate

      - name: Verify generated files
        run: |
          test -f resources/js/types/generated.d.ts
          test -f resources/js/types/zod-schemas.ts

      - name: Build frontend
        run: npm run build

      - name: Run PHP tests
        run: php artisan test

      - name: Run TypeScript tests
        run: npm test
```

### GitLab CI

#### .gitlab-ci.yml

```yaml
stages:
  - build
  - test

variables:
  NODE_VERSION: "18"
  PHP_VERSION: "8.2"

cache:
  paths:
    - vendor/
    - node_modules/

build:
  stage: build
  image: php:${PHP_VERSION}
  before_script:
    - curl -sL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
    - apt-get install -y nodejs
    - composer install --prefer-dist --no-progress
    - npm ci
  script:
    - php artisan typescript:transform
    - php artisan schema:generate
    - npm run build
  artifacts:
    paths:
      - public/
      - resources/js/types/

test:
  stage: test
  dependencies:
    - build
  script:
    - php artisan test
    - npm test
```

## Development Workflow Integration

### File Watching

#### Using Chokidar (Node.js)

Create a watch script to regenerate schemas when validation files change:

```javascript
// scripts/watch-schemas.js
const chokidar = require("chokidar");
const { exec } = require("child_process");

const paths = [
  "app/Http/Requests/**/*.php",
  "app/Data/**/*.php",
  "app/Validation/**/*.php",
];

console.log("Watching for validation file changes...");

chokidar
  .watch(paths, { ignoreInitial: true })
  .on("change", (path) => {
    console.log(`\n📝 ${path} changed`);
    console.log("🔄 Regenerating Zod schemas...");

    exec("php artisan schema:generate", (error, stdout, stderr) => {
      if (error) {
        console.error("❌ Generation failed:", error.message);
        return;
      }
      if (stderr) {
        console.error("⚠️  Warning:", stderr);
      }
      console.log("✅ Zod schemas updated");
    });
  })
  .on("add", (path) => {
    console.log(`\n➕ New file: ${path}`);
    console.log("🔄 Regenerating Zod schemas...");

    exec("php artisan schema:generate", (error, stdout, stderr) => {
      if (error) {
        console.error("❌ Generation failed:", error.message);
        return;
      }
      console.log("✅ Zod schemas updated");
    });
  });
```

Add to package.json:

```json
{
  "scripts": {
    "watch:schemas": "node scripts/watch-schemas.js",
    "dev:full": "concurrently \"npm run watch:schemas\" \"npm run dev\""
  },
  "devDependencies": {
    "chokidar": "^3.5.3",
    "concurrently": "^7.6.0"
  }
}
```

#### Using inotify (Linux/Mac)

```bash
#!/bin/bash
# scripts/watch-schemas.sh

echo "Watching validation files for changes..."

inotifywait -m -r -e modify,create,delete \
  --include='\.php$' \
  app/Http/Requests app/Data app/Validation | \
while read path action file; do
  echo "📝 $path$file $action"
  echo "🔄 Regenerating Zod schemas..."
  php artisan schema:generate
  if [ $? -eq 0 ]; then
    echo "✅ Zod schemas updated"
  else
    echo "❌ Generation failed"
  fi
  echo ""
done
```

### IDE Integration

#### VSCode Task Configuration

Create `.vscode/tasks.json`:

```json
{
  "version": "2.0.0",
  "tasks": [
    {
      "label": "Generate Zod Schemas",
      "type": "shell",
      "command": "php",
      "args": ["artisan", "schema:generate"],
      "group": "build",
      "presentation": {
        "echo": true,
        "reveal": "always",
        "focus": false,
        "panel": "shared"
      },
      "problemMatcher": []
    },
    {
      "label": "Generate Types and Schemas",
      "type": "shell",
      "command": "php",
      "args": ["artisan", "typescript:transform"],
      "group": "build",
      "presentation": {
        "echo": true,
        "reveal": "always",
        "focus": false,
        "panel": "shared"
      },
      "dependsOn": "Generate Zod Schemas"
    }
  ]
}
```

#### PHPStorm Integration

Add external tools in PHPStorm:

1. Go to File → Settings → Tools → External Tools
2. Add new tool:
   - Name: Generate Zod Schemas
   - Program: php
   - Arguments: artisan schema:generate
   - Working directory: $ProjectFileDir$

## Testing Integration

### Jest Configuration

#### jest.config.js

```javascript
module.exports = {
  preset: "ts-jest",
  testEnvironment: "node",
  setupFilesAfterEnv: ["<rootDir>/tests/setup.ts"],
  moduleNameMapping: {
    "^@/(.*)$": "<rootDir>/resources/js/$1",
  },
  testMatch: ["<rootDir>/tests/typescript/**/*.test.ts"],
};
```

#### tests/setup.ts

```typescript
// Ensure schemas are generated before tests run
import { execSync } from "child_process";

beforeAll(() => {
  console.log("Generating schemas for tests...");
  execSync("php artisan schema:generate", { stdio: "inherit" });
});
```

#### Schema Validation Tests

```typescript
// tests/typescript/schemas.test.ts
import { describe, it, expect } from "@jest/globals";
import { CreateUserSchema, UpdatePostSchema } from "@/types/zod-schemas";

describe("Generated Zod Schemas", () => {
  describe("CreateUserSchema", () => {
    it("validates correct user data", () => {
      const validData = {
        name: "John Doe",
        email: "john@example.com",
        password: "securePassword123",
        password_confirmation: "securePassword123",
      };

      const result = CreateUserSchema.safeParse(validData);
      expect(result.success).toBe(true);
    });

    it("rejects invalid email", () => {
      const invalidData = {
        name: "John Doe",
        email: "not-an-email",
        password: "securePassword123",
        password_confirmation: "securePassword123",
      };

      const result = CreateUserSchema.safeParse(invalidData);
      expect(result.success).toBe(false);

      if (!result.success) {
        const emailError = result.error.issues.find(
          (issue) => issue.path[0] === "email"
        );
        expect(emailError).toBeDefined();
        expect(emailError?.code).toBe("invalid_string");
      }
    });
  });
});
```

### Playwright Integration

#### tests/e2e/form-validation.spec.ts

```typescript
import { test, expect } from "@playwright/test";
import { CreateUserSchema } from "../src/types/zod-schemas";

test.describe("Form Validation", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/register");
  });

  test("should validate form with Zod schema", async ({ page }) => {
    // Fill form with data that should match our Zod schema
    const formData = {
      name: "John Doe",
      email: "john@example.com",
      password: "securePassword123",
      password_confirmation: "securePassword123",
    };

    // Verify the data matches our schema expectations
    const result = CreateUserSchema.safeParse(formData);
    expect(result.success).toBe(true);

    // Fill the actual form
    await page.fill('[name="name"]', formData.name);
    await page.fill('[name="email"]', formData.email);
    await page.fill('[name="password"]', formData.password);
    await page.fill(
      '[name="password_confirmation"]',
      formData.password_confirmation
    );

    // Submit and verify success
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL("/dashboard");
  });

  test("should show validation errors for invalid data", async ({ page }) => {
    const invalidData = {
      name: "",
      email: "invalid-email",
      password: "123",
      password_confirmation: "different",
    };

    // Verify our schema would catch these errors
    const result = CreateUserSchema.safeParse(invalidData);
    expect(result.success).toBe(false);

    // Fill form with invalid data
    await page.fill('[name="name"]', invalidData.name);
    await page.fill('[name="email"]', invalidData.email);
    await page.fill('[name="password"]', invalidData.password);
    await page.fill(
      '[name="password_confirmation"]',
      invalidData.password_confirmation
    );

    await page.click('button[type="submit"]');

    // Verify validation errors appear
    await expect(page.locator(".error")).toBeVisible();
  });
});
```

## Production Considerations

### Schema Versioning

Track schema changes for API versioning:

```typescript
// resources/js/types/schema-versions.ts
export const SCHEMA_VERSION = "1.2.0";

export const SCHEMA_CHANGELOG = {
  "1.2.0": [
    "Added UserPreferencesSchema",
    "Updated CreatePostSchema to include category_id",
  ],
  "1.1.0": [
    "Added UpdateUserSchema",
    "Fixed email validation in ContactSchema",
  ],
  "1.0.0": ["Initial schema generation"],
};
```

Include version in generated schemas:

```php
// config/laravel-schema-generator.php
'output' => [
    'path' => resource_path('js/types/zod-schemas.ts'),
    'format' => 'module',
    'include_version' => true, // Add version header to generated file
],
```

### Performance Optimization

#### Lazy Loading Schemas

```typescript
// utils/schema-loader.ts
import { z } from "zod";

const schemaCache = new Map<string, z.ValidationSchema>();

export async function loadSchema(
  schemaName: string
): Promise<z.ValidationSchema> {
  if (schemaCache.has(schemaName)) {
    return schemaCache.get(schemaName)!;
  }

  // Dynamically import schema
  const module = await import(`@/types/schemas/${schemaName}`);
  const schema = module[`${schemaName}Schema`];

  schemaCache.set(schemaName, schema);
  return schema;
}

// Usage
const userSchema = await loadSchema("CreateUser");
const result = userSchema.safeParse(userData);
```

#### Bundle Splitting

Configure your bundler to split schemas into separate chunks:

```typescript
// vite.config.ts
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          "zod-schemas": ["./resources/js/types/zod-schemas.ts"],
        },
      },
    },
  },
});
```

### Error Monitoring

#### Sentry Integration

```typescript
// utils/validation-errors.ts
import * as Sentry from "@sentry/browser";
import { z } from "zod";

export function reportValidationError(
  schema: z.ValidationSchema,
  data: unknown,
  context: Record<string, any> = {}
): void {
  const result = schema.safeParse(data);

  if (!result.success) {
    Sentry.withScope((scope) => {
      scope.setTag("error_type", "validation_error");
      scope.setContext("validation", {
        schema: schema.constructor.name,
        errors: result.error.issues,
        data: JSON.stringify(data),
        ...context,
      });

      Sentry.captureMessage("Client-side validation failed");
    });
  }
}
```

#### Custom Error Tracking

```typescript
// utils/error-tracker.ts
interface ValidationError {
  timestamp: Date;
  schema: string;
  field: string;
  error: string;
  data: unknown;
  userAgent: string;
  url: string;
}

class ValidationErrorTracker {
  private errors: ValidationError[] = [];

  track(schema: string, field: string, error: string, data: unknown): void {
    this.errors.push({
      timestamp: new Date(),
      schema,
      field,
      error,
      data,
      userAgent: navigator.userAgent,
      url: window.location.href,
    });

    // Send to analytics service
    this.sendToAnalytics();
  }

  private async sendToAnalytics(): Promise<void> {
    if (this.errors.length >= 10) {
      await fetch("/api/validation-errors", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(this.errors),
      });

      this.errors = [];
    }
  }
}

export const validationTracker = new ValidationErrorTracker();
```

## Best Practices

### Automate Schema Generation

Always regenerate schemas as part of your build process to ensure consistency.

### Version Your Schemas

Track schema changes alongside your API versions for better client compatibility.

### Test Schema Generation

Include schema generation in your CI pipeline and test the generated schemas.

### Monitor Validation Errors

Track client-side validation failures to identify issues with schema generation or data flow.

### Cache Generated Schemas

In production, consider caching or lazy-loading schemas for better performance.

### Document Schema Changes

Maintain a changelog of schema modifications for your frontend team.

```typescript
// Add to generated files
/**
 * Generated Zod schemas from Laravel validation rules
 * Generated at: 2024-01-15 14:30:00
 * Laravel Zod Generator version: 2.1.0
 *
 * DO NOT EDIT MANUALLY - This file is auto-generated
 *
 * Changes in this version:
 * - Added UserPreferencesSchema
 * - Updated CreatePostSchema validation rules
 */
```

## Next Steps

- [Examples](../examples/real-world.md) - See integration examples in real projects
- [Reference](../reference/troubleshooting.md) - Debug integration issues
- [Custom Extractors](./custom-extractors.md) - Handle complex integration scenarios
- [Custom Type Handlers](./custom-type-handlers.md) - Customize integration behavior
