# Laravel Docbot

Generate beautiful API documentation and custom command listings for your Laravel 12 projects (PHP 8.2+) — effortlessly.

## 🚀 Introduction

**Laravel Docbot** is a developer-focused package that automates the generation of API route documentation (Markdown & Postman collections) and lists all custom Artisan commands in your Laravel project. Designed for modern teams, it streamlines onboarding, API sharing, and internal documentation.

## Main Use Cases

- **API Documentation:** Instantly generate Markdown and Postman v2.1 collections for all your API routes, segmented by prefix.
- **Custom Command Listing:** List all custom Artisan commands, excluding built-in Laravel commands, for easy project overview.
- **Configurable Output:** Customize output directories, route segments, and command exclusions via `config/docbot.php`.

## Quick Installation

```bash
composer require equidna/laravel-docbot
```

> The package uses Laravel's auto-discovery. To customize configuration:

```bash
php artisan vendor:publish --tag=laravel-docbot:config
```

## Usage Examples

### Generate API Documentation

```bash
php artisan docbot:routes
```

- Outputs Markdown and Postman JSON files to `doc/routes/<segment>/`
- Use `--segment=api` (repeatable) to limit generation to specific segment keys.
- Use `--format=markdown` or `--format=postman` to generate only the required artifact type (defaults to both).
- Use `--continue-on-error` to keep processing the remaining writers even if one format fails (Docbot still reports the failure at the end).

### List Custom Artisan Commands

```bash
php artisan docbot:commands
```

- Outputs Markdown to `doc/commands/project_commands.md`

## Configuration

Edit `config/docbot.php` to:

- Set output directories
- Define route segments (prefixes, domains, middleware filters)
- Configure default hosts/authentication for route documentation
- Exclude namespaces/commands from listings
- Register custom route writers and command filters/writers

Example segment config:

```php
'segments' => [
  [
    'key' => 'api',
    'prefix' => 'api/',
    'host_variable' => 'API_HOST',
    'host_value' => env('DOCBOT_API_HOST', 'https://api.example.com'),
    'auth' => [
      'type' => 'bearer',
      'token_variable' => 'API_TOKEN',
      'header' => 'Authorization',
    ],
  ],
    // ...
],
```

Use the `route_defaults` section to define fallback host placeholders and authentication schemes for segments that do not override them. Set `auth.type` to `none` for public segments such as `web`.

### Output directory safety

Path traversal remains a common attack vector in Laravel apps ([StackHawk](https://www.stackhawk.com/blog/laravel-path-traversal-guide-examples-and-prevention/), [OWASP](https://owasp.org/www-community/attacks/Path_Traversal), [HackerOne](https://www.hackerone.com/blog/preventing-directory-traversal-attacks-techniques-and-tips-secure-file-access)). To align with that guidance, Docbot now canonicalizes `docbot.output_dir` using a dedicated `PathGuard` helper and refuses to write outside `base_path()`. If the configured path escapes your project root, Docbot aborts early with a descriptive error so you can correct the value (for example, `DOCBOT_OUTPUT_DIR=doc` or `storage/docs`). This keeps generated Markdown/Postman files inside your repository while still letting you reorganize folders safely.

### Sanitization policy (filenames)

Docbot sanitizes segment keys when producing filesystem artifacts (for example `doc/routes/<segment>/`). The behavior is centralized in `\Equidna\LaravelDocbot\Routing\Support\Sanitizer::filename()` and can be customized via `config/docbot.php` under the `sanitization.filename` keys.

Example — override the default pattern/replacement/fallback in your published config:

```php
// config/docbot.php
'sanitization' => [
  'filename' => [
    // PCRE pattern used to find runs of disallowed characters
    'pattern' => '/[^A-Za-z0-9._-]+/',
    // Replacement for matched runs
    'replacement' => '-',
    // Fallback name when the sanitized result would be empty
    'fallback' => 'unknown',
  ],
],
```

Quick example — expected effect

```php
// Suppose you configure a segment key like this:
$segmentKey = 'My API / v1';

// With the default sanitization the filename becomes:
// Sanitizer::filename('My API / v1') === 'My-API-v1'

// If you prefer underscores instead of hyphens, set replacement => '_':
// 'sanitization.filename.replacement' => '_'
// Sanitizer::filename('My API / v1') === 'My_API_v1'
```

Notes:

- The default pattern replaces any character that is not A-Z, a-z, 0-9, dot, underscore or hyphen with `-`.
- The `fallback` value is used when the sanitized result would be empty or ambiguous (for example when the configured key is `.` or `..`).
- `Sanitizer::filename()` will fall back to sensible defaults if the config system is not available (for example during early boot or in non-Laravel contexts).

### Swapping Writers & Filters

Docbot now exposes explicit extension points via `config/docbot.php`:

- `routes.writers`: array of classes implementing `Equidna\LaravelDocbot\Contracts\RouteWriter`. Provide your own class to output alternative formats (e.g., OpenAPI, HTML).
- `routes.collector`: class implementing `Equidna\LaravelDocbot\Contracts\RouteCollector`. Swap out the default router-based collector if you source routes from elsewhere.
- `routes.segment_resolver`: class implementing `Equidna\LaravelDocbot\Contracts\RouteSegmentResolver` to alter how segments are built.
- `commands.filters`: array of classes implementing `Equidna\LaravelDocbot\Contracts\CommandFilter`. Use this to skip framework-specific namespaces or generated commands.
- `commands.writer`: class implementing `Equidna\LaravelDocbot\Contracts\CommandWriter`. Replace the Markdown writer with JSON, HTML, etc.
- `commands.output_filename`: tweak the Markdown filename without replacing the writer.

Each writer/filter is resolved through the Laravel service container, so you may leverage dependency injection for config, loggers, HTTP clients, etc.

## Technical Overview

- **Command-based workflow:** All features are implemented as Artisan commands.
- **API docs:** Reads route definitions directly from Laravel's router, segments them via configurable prefixes/middleware, and extracts controller docblocks for descriptions.
- **Postman collections:** Generated per segment, compatible with Postman v2.1 schema.
- **Custom command listing:** Filters out built-in commands using config-driven lists.
- **Extensibility:** Route/command pipelines resolve contracts (`RouteCollector`, `RouteSegmentResolver`, `RouteWriter`, `CommandFilter`, `CommandWriter`) so projects can override behavior via config bindings.
- **Output:**
  - API docs: `doc/routes/<segment>/`
  - Command docs: `doc/commands/`

## Development Instructions

- **Code Style:** PSR-12 (see Coding Standards)
- **Testing:**
  - Run tests: `vendor/bin/phpunit`
  - Static analysis: `vendor/bin/phpstan analyse src/ --level=max`
- **Configuration:** Edit `config/docbot.php` for custom output and exclusions.

---

For more details, see the source code and configuration files. Contributions welcome!

## Changelog

All notable changes to this project are documented in `CHANGELOG.md`.

### Unreleased

- Remove `declare(strict_types=1)` from source files to align with the project's coding standard and tooling.
- Improve filesystem error handling in writers (Markdown/Postman/Commands): writers now ensure directories exist, validate JSON encoding where applicable, and throw descriptive errors on failure.
- Detect duplicate route writer formats during service resolution and surface a clear configuration error instead of silently overwriting writers.
