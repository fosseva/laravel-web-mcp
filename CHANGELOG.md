# Changelog

## 0.1.0-alpha.1 — 2026-10-06

First alpha preview for developers experimenting with browser-native WebMCP in Laravel.

- Expose existing Laravel AI SDK tools through Blade by class or discovered name.
- Reuse tool descriptions, input schemas, handlers, and validation.
- Check authorization when listing and executing tools, with session-bound exposure tokens and CSRF protection.
- Refresh browser registrations after navigation, Livewire updates, and authentication changes.
- Include a getting-started demo, Chrome setup instructions, and PHP/JavaScript CI checks.

### Preview limitations

The package is under active development. APIs and configuration may change between prereleases. Browser and agent compatibility depend on the evolving WebMCP implementation.

The bridge calls local SDK handlers directly. SDK agent middleware, conversation state, and invocation events are not applied. Calls requiring SDK approval are rejected; browser approval workflows are not supported.

Requires PHP 8.3+, Laravel 12.62+ or 13.15+, and `laravel/ai` 1.1+.
