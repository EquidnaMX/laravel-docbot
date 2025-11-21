# Copilot Coding Agent Instructions for Laravel Docbot

## Project Overview

- **Laravel Docbot** is a package for Laravel 12 that generates API documentation and lists custom Artisan commands.
- The main entry points are:
  - `src/Console/Commands/GenerateRoutes.php`: Generates Markdown and Postman collections for API routes.
  - `src/Console/Commands/GenerateCommands.php`: Lists custom Artisan commands, excluding built-in ones.
- Configuration is managed via `config/docbot.php`.

## Architecture & Data Flow

- **Command-based workflow:** All major features are implemented as Artisan commands.
- **API documentation:**
  - Reads route definitions using `Artisan::call('route:list', ['--json' => true])`.
  - Segments routes by prefix (customizable in config).
  - Outputs Markdown and Postman JSON files to `doc/routes/<segment>/`.
- **Custom command listing:**
  - Filters out built-in commands using config-driven lists.
  - Outputs Markdown to `doc/commands/project_commands.md`.

## Developer Workflows

- **Build/Run:** No build step required; use `php artisan` commands.
- **Testing:** PHPUnit is installed; run with `vendor/bin/phpunit`.
- **Code Quality:**
  - Run `phpcbf` for PSR-12 compliance.
  - Run `vendor/bin/phpstan analyse src/ --level=max` for static analysis.
- **Configuration:**
  - Edit `config/docbot.php` to customize output directories, route segments, and command exclusions.

## Conventions & Patterns

- **PSR-12**: All PHP code must follow PSR-12 (see `Codding Standards.instructions.md`).
- **Config-driven exclusions:** Command exclusions (`exclude_namespaces`, `exclude_commands`) are set in `config/docbot.php`.
- **Output locations:**
  - API docs: `doc/routes/<segment>/`
  - Command docs: `doc/commands/`
- **No front-end assets or JS/TS code in project source.**

## Integration Points

- **Laravel Facades:** Uses `Artisan`, `File`, and `Str` facades.
- **Reflection:** Uses `ReflectionMethod` to extract controller docblocks for route descriptions.
- **Postman:** Generates collections compatible with Postman v2.1 schema.

## Examples

- To generate API docs: `php artisan docbot:routes`
- To list custom commands: `php artisan docbot:commands`
- To customize segments:
  ```php
  // config/docbot.php
  'segments' => [
      ['key' => 'api', 'prefix' => 'api/', 'token' => 'API_TOKEN'],
      // ...
  ],
  ```

## Repository Standards

- See `Codding Standards.instructions.md` for style and workflow rules.
- `.gitignore` includes standard Laravel ignores.

---

If any section is unclear or missing, please request clarification or provide feedback to improve these instructions.
