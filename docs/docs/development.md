---
sidebar_position: 5
---

# Development & Contributing

Contributing to Laravel Zod Generator is welcomed and appreciated! This guide will help you set up your development environment and understand the contribution process.

## Development Installation

For contributing to the package or testing the latest features:

```bash
git clone https://github.com/romegasoftware/laravel-schema-generator.git
cd laravel-schema-generator
composer install
```

### Running Tests

```bash
composer test
```

### Code Formatting

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards. We use PHP CS Fixer to maintain consistent code style:

```bash
composer format
```

## Contributing

We love your input! We want to make contributing to this package as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features

### Development Process

We use GitHub to host code, to track issues and feature requests, as well as accept pull requests.

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes.
5. Make sure your code lints.
6. Create that pull request!

### Pull Request Process

1. Update the README.md with details of changes to the interface, if applicable.
2. Update the CHANGELOG.md with a note describing your changes.
3. The versioning scheme we use is [SemVer](http://semver.org/).
4. Your pull request will be merged once you have the sign-off of at least one maintainer.

## Reporting Issues

We use GitHub issues to track public bugs. Report a bug by [opening a new issue](https://github.com/romegasoftware/laravel-schema-generator/issues/new).

### Great Bug Reports

Great bug reports tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

## Feature Requests

We welcome feature requests! Please open an issue with:

- A clear description of the feature
- Why you think it would be useful
- Any implementation ideas you might have

## Code Style Guidelines

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards. Additional guidelines:

- Use meaningful variable and method names
- Add type hints for all method parameters and return types
- Write docblocks for public methods
- Keep methods small and focused
- Use early returns to reduce nesting

## Testing Guidelines

- Write tests for new functionality
- Ensure all tests pass before submitting PR
- Aim for good test coverage
- Use descriptive test method names
- Test edge cases and error conditions

## Project Structure

Understanding the project structure will help you contribute effectively:

```
src/
├── Attributes/           # ValidationSchema attribute
├── Commands/            # Artisan commands
├── Contracts/           # Interfaces and contracts
├── Exceptions/          # Custom exceptions
├── Extractors/          # Rule extraction logic
├── Generators/          # Schema generation logic
├── Services/            # Core services
└── TypeHandlers/        # Type conversion handlers

tests/
├── Feature/             # Integration tests
├── Unit/               # Unit tests
└── Fixtures/           # Test data and classes

config/
└── laravel-schema-generator.php  # Package configuration
```

## Development Workflow

1. **Fork and Clone**: Fork the repository and clone your fork locally
2. **Create Branch**: Create a feature branch from `main`
3. **Make Changes**: Implement your changes with tests
4. **Run Tests**: Ensure all tests pass
5. **Format Code**: Run the code formatter
6. **Update Docs**: Update documentation if needed
7. **Submit PR**: Create a pull request with a clear description

## Local Testing

To test your changes against a real Laravel project:

1. Create a test Laravel project
2. Add your local package as a path repository in `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-schema-generator"
    }
  ]
}
```

3. Require the package: `composer require romegasoftware/laravel-schema-generator`

## Documentation

Documentation is built with Docusaurus and located in the `docs/` directory. To work on documentation:

```bash
cd docs
npm install
npm start
```

This will start a local development server for the documentation site.

## Community Resources

- **GitHub Issues**: [Report bugs and feature requests](https://github.com/romegasoftware/laravel-schema-generator/issues)
- **GitHub Discussions**: [Community Q&A and discussions](https://github.com/romegasoftware/laravel-schema-generator/discussions)

## License

By contributing, you agree that your contributions will be licensed under the same [MIT License](http://choosealicense.com/licenses/mit/) that covers the project.

When you submit code changes, your submissions are understood to be under the same MIT License that covers the project.
