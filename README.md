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
