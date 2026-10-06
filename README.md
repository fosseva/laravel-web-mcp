# Laravel WebMCP

Reuse Laravel AI SDK tools in a Blade page. The SDK owns the tool's description, schema, and handler; this package adds explicit browser opt-in and a session-authenticated WebMCP transport.

Requires PHP 8.3+, Laravel 12.62+ or 13.15+, and `laravel/ai` 1.1+.

## Install

```bash
composer require fosseva/laravel-web-mcp
```

The Laravel AI SDK is installed as a dependency. Use its `php artisan make:tool` command to create tools.

## Opt in an existing SDK tool

Keep the SDK's `Laravel\Ai\Contracts\Tool` interface and add `Fosseva\WebMcp\Contracts\WebMcp` alongside it. The browser contract is independent of the SDK contract; the package requires both. The optional `ProvidesWebMcpDefaults` trait implements all three browser methods: a snake_case class name, empty annotations, and authorization that returns `false`. Override only the defaults you need. Implement `webMcpAuthorize()` with your application policy to allow browser access.

```php
namespace App\Ai\Tools;

use Fosseva\WebMcp\Concerns\ProvidesWebMcpDefaults;
use Fosseva\WebMcp\Contracts\WebMcp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Laravel\Ai\Tools\Request as ToolRequest;
use Laravel\Ai\Contracts\Tool;

class SearchProducts implements Tool, WebMcp
{
    use ProvidesWebMcpDefaults;

    public function description(): string
    {
        return 'Search the products available to the current user.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->min(2)->max(100)->required()];
    }

    public function webMcpAuthorize(Request $request): bool
    {
        return $request->user()?->can('viewAny', \App\Models\Product::class) ?? false;
    }

    public function handle(ToolRequest $request): string
    {
        $input = $request->validate(['query' => ['required', 'string', 'min:2', 'max:100']]);

        return \App\Models\Product::query()
            ->where('name', 'like', '%'.$input['query'].'%')
            ->limit(10)->get(['id', 'name'])->toJson();
    }

    public function webMcpAnnotations(): array
    {
        return ['readOnlyHint' => true];
    }
}
```

Preserve tenant filtering and resource authorization in the handler. Schema metadata helps the browser construct arguments; it is not server validation. Use the SDK request's `validate()` inside the existing handler so both SDK and browser invocations enforce the same rules.

To override the name, implement `webMcpName(): string`. Names must be unique on the page and contain 1–128 letters, digits, underscores, dots, or hyphens. Browser annotations are hints, never permission grants.

## Expose it in Blade

```blade
<x-webmcp::expose :tool="\App\Ai\Tools\SearchProducts::class" />
```

Render the component on the page that needs the tool. It loads the browser runtime once, even when several tools are exposed. No provider registration, directory scanning, parallel tool definitions, or hand-written JavaScript is required. Only explicitly opted-in classes rendered by Blade can receive an exposure token.

Blade issues encrypted exposure tokens bound to the current session and valid for 60 minutes. The browser retrieves the selected tools' SDK schemas and registers them with the native WebMCP API. Calls run the original SDK handler through Laravel's container with an SDK `ToolRequest`; string / `Stringable` results are preserved as text. JSON returned by a handler remains JSON text.

`webMcpAuthorize()` runs when listing tools and again immediately before execution. Invalid, tampered, expired, or other-session tokens cannot resolve tools. Tokens remain valid until expiry within their issuing session; removing a component unregisters the browser tool but does not revoke an already issued token. Resource authorization remains the server's responsibility. Reload after expiry or after a session ID change.

## Configuration

```bash
php artisan vendor:publish --tag=webmcp-config
```

```php
return [
    'enabled' => env('WEBMCP_ENABLED', true),
    'path' => '_webmcp',
    'middleware' => ['web'],
    'exposure_ttl' => 60,
];
```

Keep `web` middleware for sessions and CSRF protection. Add `auth` and rate limiting for applications that require them. Tool-specific authorization belongs in `webMcpAuthorize()` and the handler. Clear Laravel's route cache after changing route configuration. Render exposures per request; do not serve cached Blade output across sessions.

Routes are `GET /_webmcp/manifest`, `POST /_webmcp/execute`, and `GET /_webmcp/runtime.js`. The runtime contains no user data. Manifests are private and their ETags include the CSRF token. HTTP endpoints preserve Laravel errors, including 403, 404, 409, and validation errors with status 422. The registered browser tool returns these as JSON text containing `error.status`, `error.message`, and `error.fields`, so an agent can inspect validation failures and recover.

## Browser lifecycle

Use a browser or agent environment with native WebMCP support and a secure context (HTTPS or localhost). Both `document.modelContext` and `navigator.modelContext` are supported. Without that API, no tools are registered; ordinary page controls still work.

The runtime resynchronizes on focus, Livewire navigation, and Livewire DOM updates. It removes registrations when their Blade markers disappear. Notify it after changing authentication without navigation:

```js
document.dispatchEvent(new CustomEvent('webmcp:auth-changed'));
```

Events: `webmcp:registered`, `webmcp:unregistered`, `webmcp:sync`, and `webmcp:error`. The runtime retries a CSRF failure once after refreshing the manifest. It never retries a successful mutation.

## SDK boundaries

This is a transport for local SDK tool handlers, not an SDK agent runner or an MCP server. It does not prompt a model, require provider API keys, or apply agent middleware, conversation state, tool invocation events, or agent-specific constructor arguments. Tools are resolved from the Laravel container for each request; bind dependencies explicitly and avoid singleton tools carrying user state.

If an SDK `Approvable` tool requests approval for the current arguments, execution fails with HTTP 409. SDK approval workflows are not implemented by this browser bridge and are never silently bypassed. Provider-hosted tools and remote MCP tools are outside this package's scope.

## Development

```bash
composer install
composer test
node --test tests/runtime.test.cjs
```

PHP tests cover real SDK execution, validation, authorization changes, session binding, expiry, tampering, approval requirements, and CSRF-aware manifest caching. Runtime tests cover registration lifecycle and execution failures.
