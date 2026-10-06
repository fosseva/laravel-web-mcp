# Laravel WebMCP

Make your Laravel AI SDK tools available to browser agents through Blade. The same tool supplies its description, input schema, and handler for both SDK and WebMCP calls.

You choose which tools each page exposes. Browser calls use Laravel sessions, authorization, and CSRF protection.

Requires PHP 8.3+, Laravel 12.62+ or 13.15+, and `laravel/ai` 1.1+.

## Install

```bash
composer require fosseva/laravel-web-mcp
```

The Laravel AI SDK is installed as a dependency. Use its `php artisan make:tool` command to create tools.

## Opt in an existing SDK tool

Keep `Laravel\Ai\Contracts\Tool` on your tool class and add `Fosseva\WebMcp\Contracts\WebMcp`. Both interfaces are required.

`Tool` defines the description, schema, and handler. `WebMcp` adds the browser tool name, behavior hints, and authorization check.

Use the optional `ProvidesWebMcpDefaults` trait to supply these defaults:

| Method | Default |
| --- | --- |
| `webMcpName()` | The class name in snake_case, such as `search_products`. |
| `webMcpAnnotations()` | An empty array of behavior hints. |
| `webMcpAuthorize()` | `false`, denying browser access. |

Override `webMcpAuthorize()` with your application's authorization policy to allow access. Override the name or annotations only when you need different values.

```php
namespace App\Ai\Tools;

use Fosseva\WebMcp\Concerns\ProvidesWebMcpDefaults;
use Fosseva\WebMcp\Contracts\WebMcp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;

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

The schema tells the browser which arguments to send. Validate those arguments with the SDK request's `validate()` method inside `handle()`. This applies the same validation to SDK and browser calls.

Keep tenant filtering and resource authorization in the handler as well.

Override `webMcpName()` to choose a custom name. Names must be unique on the page and contain 1–128 letters, digits, underscores, dots, or hyphens.

Use `description()` for the tool's description. Use `webMcpAnnotations()` for boolean behavior hints, such as `readOnlyHint`. Annotations do not grant permissions.

## Expose it in Blade

```blade
<x-webmcp::expose :tool="\App\Ai\Tools\SearchProducts::class" />
```

Add this component to the page that needs the tool. It loads the browser runtime once, even when you expose several tools.

Each tool must implement both contracts and be rendered by Blade to receive an exposure token.

Blade creates an encrypted token for each selected tool. The token belongs to the current session and expires after 60 minutes by default.

The browser loads the tool definitions and registers them with the native WebMCP API. Each call resolves the tool through Laravel's container and passes its arguments to the existing SDK handler.

The handler's `string` or `Stringable` result is returned as text. If the handler returns JSON text, the bridge preserves it.

`webMcpAuthorize()` runs when listing tools and immediately before each call. The server rejects invalid, tampered, expired, or other-session tokens.

Removing a component unregisters the tool in the browser. Its issued token remains valid until expiry, so the server must continue to check authorization.

Reload the page when a token expires or the session ID changes.

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

Keep the `web` middleware for sessions and CSRF protection. Add `auth` and rate limiting as needed. Check tool-specific permissions in `webMcpAuthorize()` and the handler.

Clear Laravel's route cache after changing route configuration. Render tool exposures for each request; their tokens must not be shared through cached Blade output.

## Routes and errors

The package registers three routes:

| Route | Purpose |
| --- | --- |
| `GET /_webmcp/manifest` | Load the selected tool definitions. |
| `POST /_webmcp/execute` | Execute an authorized tool call. |
| `GET /_webmcp/runtime.js` | Load the browser runtime. |

The runtime contains no user data. Tool manifests are private, and their cache identifiers include the CSRF token.

HTTP endpoints preserve Laravel error responses, including validation errors with status 422. The browser tool returns errors as JSON text with `error.status`, `error.message`, and `error.fields`. This lets an agent inspect the failure and correct its input.

## Browser lifecycle

Use a browser or agent environment with native WebMCP support over HTTPS or localhost. The runtime supports both `document.modelContext` and `navigator.modelContext`.

If WebMCP is unavailable, the runtime leaves ordinary page controls working and does not register tools.

The runtime refreshes tool registrations when the window gains focus, after Livewire navigation, and after Livewire DOM updates. It unregisters tools whose Blade markers disappear.

If authentication changes without navigation, notify the runtime:

```js
document.dispatchEvent(new CustomEvent('webmcp:auth-changed'));
```

Listen for `webmcp:registered`, `webmcp:unregistered`, `webmcp:sync`, and `webmcp:error` to track runtime activity.

After a CSRF failure, the runtime refreshes the manifest and retries once. Successful calls are not retried.

## SDK boundaries

The bridge executes local SDK tool handlers directly. It does not run an SDK agent or prompt a model, so the bridge itself needs no provider API key.

Agent middleware, conversation state, tool invocation events, and agent-specific constructor arguments are not applied. Bind tool dependencies in Laravel's container and keep user-specific state out of singleton tools.

If an SDK `Approvable` tool requests approval, the bridge rejects the call with HTTP 409. Browser approval workflows are not supported.

Provider-hosted tools and remote MCP tools are outside this package's scope.

## Development

```bash
composer install
composer test
node --test tests/runtime.test.cjs
```

PHP tests cover SDK execution, validation, authorization, session tokens, approval requirements, and manifest caching.

JavaScript tests cover browser registration, execution errors, and CSRF retries.
