# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

- **Add OpenAPI 3.0 support:** New `OpenApiRouteWriter` generates industry-standard OpenAPI/Swagger specifications in YAML format for enhanced API documentation and tooling integration.
- Remove `declare(strict_types=1)` from source files to align with the project's coding standard and tooling.
- Improve filesystem error handling in writers (Markdown/Postman/Commands): writers now ensure directories exist, validate JSON encoding where applicable, and throw descriptive errors on failure.
- Detect duplicate route writer formats during service resolution and surface a clear configuration error instead of silently overwriting writers.
- Guard `docbot.output_dir` with a new `PathGuard` helper so documentation output always stays inside `base_path()` (mitigates path traversal attempts).
- Add `--continue-on-error` to `docbot:routes` so long-running documentation jobs can finish remaining formats/segments while still reporting failed writers.

## [0.1] - Initial release

- Initial package scaffolding and core functionality: route documentation generation (Markdown & Postman), command listing, configuration, and service provider bindings.
