# Contributing

We love your input! We want to make contributing to this package as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features

## Development Process

We use GitHub to host code, to track issues and feature requests, as well as accept pull requests.

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes.
5. Make sure your code lints.
6. Issue that pull request!

## Development Setup

1. Clone the repository:
```bash
git clone https://github.com/romegasoftware/laravel-zod-generator.git
cd laravel-zod-generator
```

2. Install dependencies:
```bash
composer install
```

3. Run tests:
```bash
composer test
```

4. Run code style fixer:
```bash
composer format
```

## Pull Request Process

1. Update the README.md with details of changes to the interface, if applicable.
2. Update the CHANGELOG.md with a note describing your changes.
3. The versioning scheme we use is [SemVer](http://semver.org/).
4. Your pull request will be merged once you have the sign-off of at least one maintainer.

## Any contributions you make will be under the MIT Software License

When you submit code changes, your submissions are understood to be under the same [MIT License](http://choosealicense.com/licenses/mit/) that covers the project.

## Report bugs using GitHub's [issues](../../issues)

We use GitHub issues to track public bugs. Report a bug by [opening a new issue](../../issues/new).

**Great Bug Reports** tend to have:

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

## Code Style

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards. We use PHP CS Fixer to maintain consistent code style.

- Use meaningful variable and method names
- Add type hints for all method parameters and return types
- Write docblocks for public methods
- Keep methods small and focused
- Use early returns to reduce nesting

## Testing

- Write tests for new functionality
- Ensure all tests pass before submitting PR
- Aim for good test coverage
- Use descriptive test method names
- Test edge cases and error conditions

## License

By contributing, you agree that your contributions will be licensed under its MIT License.