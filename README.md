# Laravel Docbot

Generate beautiful API documentation and custom command listings for your Laravel 12 projects (PHP 8.1+) — effortlessly.

## 🚀 Introduction

**Laravel Docbot** is a developer-focused package that automates the generation of API route documentation (Markdown, Postman collections & OpenAPI/Swagger) and lists all custom Artisan commands in your Laravel project. Designed for modern teams, it streamlines onboarding, API sharing, and internal documentation.

## Main Use Cases

- **API Documentation:** Instantly generate Markdown, Postman v2.1 collections, and OpenAPI 3.0 specifications for all your API routes, segmented by prefix.
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

- Outputs Markdown, Postman JSON, and OpenAPI YAML files to `doc/routes/<segment>/`
- Use `--segment=api` (repeatable) to limit generation to specific segment keys.
- Use `--format=markdown`, `--format=postman`, or `--format=openapi` to generate only the required artifact type (defaults to all).
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

### OpenAPI (Swagger) Documentation

The OpenAPI writer generates industry-standard OpenAPI 3.0 specifications in YAML format. These can be:

- Imported into **Swagger UI** for interactive API documentation
- Used with **Redoc** for beautiful, customizable API docs
- Imported into **Postman** or **Insomnia** for API testing
- Used for **client SDK generation** with tools like OpenAPI Generator
- Integrated with **API gateways** and monitoring tools

**Features:**

- Automatically extracts route parameters and converts them to OpenAPI format
- Generates security schemes from your auth configuration (Bearer, API Key)
- Groups endpoints by tags derived from route names
- Includes standard HTTP response codes (200, 400, 401, 404, 500)
- Adds request bodies for POST/PUT/PATCH endpoints
- Extracts descriptions from controller docblocks

**Example output file:** `doc/routes/api/api.yaml`

You can serve this file with Swagger UI in your Laravel app or use it with external documentation tools. The specification follows the OpenAPI 3.0 standard, ensuring broad compatibility with the API tooling ecosystem.

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

- `routes.writers`: array of classes implementing `Equidna\LaravelDocbot\Contracts\RouteWriter`. The package includes Markdown, Postman, and OpenAPI writers out of the box. Provide your own class to output alternative formats (e.g., HTML, AsyncAPI).
- `routes.collector`: class implementing `Equidna\LaravelDocbot\Contracts\RouteCollector`. Swap out the default router-based collector if you source routes from elsewhere.
- `routes.segment_resolver`: class implementing `Equidna\LaravelDocbot\Contracts\RouteSegmentResolver` to alter how segments are built.
- `commands.filters`: array of classes implementing `Equidna\LaravelDocbot\Contracts\CommandFilter`. Use this to skip framework-specific namespaces or generated commands.
- `commands.writer`: class implementing `Equidna\LaravelDocbot\Contracts\CommandWriter`. Replace the Markdown writer with JSON, HTML, etc.
- `commands.output_filename`: tweak the Markdown filename without replacing the writer.

Each writer/filter is resolved through the Laravel service container, so you may leverage dependency injection for config, loggers, HTTP clients, etc.

## Technical Overview

- **Command-based workflow:** All features are implemented as Artisan commands.
- **API docs:** Reads route definitions directly from Laravel's router, segments them via configurable prefixes/middleware, and extracts controller docblocks for descriptions.
- **Multiple formats:**
  - Markdown tables for human-readable documentation
  - Postman v2.1 collections for API testing
  - OpenAPI 3.0 specifications for industry-standard API documentation and tooling integration
- **Custom command listing:** Filters out built-in commands using config-driven lists.
- **Extensibility:** Route/command pipelines resolve contracts (`RouteCollector`, `RouteSegmentResolver`, `RouteWriter`, `CommandFilter`, `CommandWriter`) so projects can override behavior via config bindings.
- **Output:**
  - API docs: `doc/routes/<segment>/`
  - Command docs: `doc/commands/`

Additional internals (recent changes):

- **Centralized file I/O:** Writers now delegate filesystem writes to a shared `WriterFilesystem` helper which ensures directories exist, centralizes error handling, and provides consistent behavior across Markdown/Postman/OpenAPI/Command writers.
- **Safer output paths:** `PathGuard` canonicalizes and validates configured output directories and will refuse to write files outside `base_path()`, preventing accidental path traversal or writes to unexpected locations.

## Development Instructions

- **Code Style:** PSR-12 (see Coding Standards)
- **Configuration:** Edit `config/docbot.php` for custom output and exclusions.

---

For more details, see the source code and configuration files. Contributions welcome!

## Changelog

All notable changes to this project are documented in `CHANGELOG.md`.

### Unreleased

See `CHANGELOG.md` for full release history. The most recent release is `0.2.0` (2025-11-20), which includes:

- Centralized filesystem writes via `WriterFilesystem` and improved filesystem error handling in all writers.
- Safer output path canonicalization and enforcement via `PathGuard` (prevents writes outside `base_path()`).
- PHPStan & docblock fixes and other quality improvements.

Future, unreleased changes will be tracked under the `Unreleased` heading in `CHANGELOG.md`.
