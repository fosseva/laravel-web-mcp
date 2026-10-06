<?php

use Fosseva\WebMcp\Concerns\ProvidesWebMcpDefaults;
use Fosseva\WebMcp\Contracts\WebMcp;
use Fosseva\WebMcp\PageTools;
use Fosseva\WebMcp\Tests\Fixtures\ApprovalTool;
use Fosseva\WebMcp\Tests\Fixtures\DefaultTool;
use Fosseva\WebMcp\Tests\Fixtures\Greet;
use Fosseva\WebMcp\Tests\Fixtures\SdkOnlyTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/fixture', function () {
        return Blade::render('<x-webmcp::expose :tool="$class" />', ['class' => request()->query('approval') ? ApprovalTool::class : Greet::class]);
    });
});

function exposureToken($test, bool $approval = false): string
{
    $html = $test->get('/fixture'.($approval ? '?approval=1' : ''))->assertOk()->getContent();
    preg_match('/data-webmcp-tool="([^"]+)"/', $html, $matches);

    $test->withCookie(config('session.cookie'), session()->getId())->withCredentials();

    return html_entity_decode($matches[1]);
}

function executionUrl(string $token): string
{
    return '/_webmcp/execute?'.http_build_query(['tool' => $token]);
}

test('Blade reuses SDK schema and exposes only selected tools', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    $this->getJson('/_webmcp/manifest')->assertOk()->assertJsonCount(0, 'tools');
    $response = $this->getJson('/_webmcp/manifest?'.http_build_query(['tools' => [$token]]))
        ->assertOk()->assertJsonCount(1, 'tools')->assertJsonPath('tools.0.name', 'greet')
        ->assertJsonPath('tools.0.inputSchema.properties.name.type', 'string')
        ->assertJsonPath('tools.0.inputSchema.properties.name.maxLength', 80)
        ->assertJsonPath('tools.0.inputSchema.required', ['name']);
    $this->postJson($response->json('tools.0.execution.url'), ['name' => 'Aniket'])
        ->assertOk()->assertJsonPath('result', 'Hello, Aniket!')->assertSessionHas('greeted', 'Aniket');
});

test('SDK validation rejects bad input before side effects', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    $this->postJson(executionUrl($token), [])->assertUnprocessable()->assertJsonValidationErrors('name')->assertSessionMissing('greeted');
});

test('authorization is checked again after rendering Blade', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    $this->withSession(['allowed' => false]);
    session()->save();
    $this->getJson('/_webmcp/manifest?'.http_build_query(['tools' => [$token]]))->assertJsonCount(0, 'tools');
    $this->postJson(executionUrl($token), ['name' => 'Aniket'])->assertForbidden()->assertSessionMissing('greeted');
});

test('tool names and arbitrary classes cannot bypass Blade exposure', function (string $selection) {
    $this->withSession(['allowed' => true]);
    $this->postJson(executionUrl($selection), ['name' => 'Aniket'])->assertNotFound()->assertSessionMissing('greeted');
})->with(['greet', Greet::class, 'App\\SecretTool', 'tampered-token']);

test('an exposure cannot be replayed in another session', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    session()->migrate(true);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->postJson(executionUrl($token), ['name' => 'Aniket'])->assertNotFound()->assertSessionMissing('greeted');
});

test('expired exposures fail closed', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    $this->travel(61)->minutes();
    $this->postJson(executionUrl($token), ['name' => 'Aniket'])->assertNotFound();
});

test('SDK approvals are never silently bypassed', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this, true);
    $this->postJson(executionUrl($token), ['name' => 'Aniket'])->assertStatus(409)->assertSessionMissing('greeted');
});

test('only explicitly opted in classes may be rendered', function () {
    expect(fn () => app(PageTools::class)->expose(stdClass::class))->toThrow(InvalidArgumentException::class);
});

test('a rotated CSRF token invalidates the manifest ETag', function () {
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    $url = '/_webmcp/manifest?'.http_build_query(['tools' => [$token]]);
    $first = $this->getJson($url)->assertOk();
    $this->withHeader('If-None-Match', $first->headers->get('ETag'))->getJson($url)->assertStatus(304);
    session()->regenerateToken();
    session()->save();
    $this->getJson($url)->assertOk()->assertJsonPath('csrfToken', session()->token());
});

test('disabling the bridge renders no exposure or runtime', function () {
    config(['webmcp.enabled' => false]);
    $this->get('/fixture')->assertOk()->assertDontSee('data-webmcp-tool')->assertDontSee('webmcp-config');
});

test('the metadata trait alone does not opt an SDK tool into browser access', function () {
    expect(fn () => app(PageTools::class)->expose(SdkOnlyTool::class))->toThrow(InvalidArgumentException::class);
});

test('repeated exposures load the runtime once and share one session token', function () {
    Route::middleware('web')->get('/repeated', function () {
        return Blade::render('<x-webmcp::expose :tool="$class" /><x-webmcp::expose :tool="$class" />', ['class' => Greet::class]);
    });
    $html = $this->get('/repeated')->assertOk()->getContent();
    expect(substr_count($html, 'id="webmcp-config"'))->toBe(1);
    expect(substr_count($html, 'defer data-webmcp-runtime'))->toBe(1);
    preg_match_all('/data-webmcp-tool="([^"]+)"/', $html, $matches);
    expect($matches[1])->toHaveCount(2);
    expect(array_unique($matches[1]))->toHaveCount(1);
});

test('the exposure contract alone cannot expose a class without the SDK tool contract', function () {
    $tool = new class implements WebMcp
    {
        use ProvidesWebMcpDefaults;

        public function webMcpAuthorize(Request $request): bool
        {
            return true;
        }
    };

    expect(fn () => app(PageTools::class)->expose($tool::class))->toThrow(InvalidArgumentException::class);
});

test('trait defaults deny browser access until authorization is explicitly configured', function () {
    Route::middleware('web')->get('/defaults', function () {
        return Blade::render('<x-webmcp::expose :tool="$class" />', ['class' => DefaultTool::class]);
    });
    $html = $this->get('/defaults')->assertOk()->getContent();
    preg_match('/data-webmcp-tool="([^"]+)"/', $html, $matches);
    $token = html_entity_decode($matches[1]);
    $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();

    $this->getJson('/_webmcp/manifest?'.http_build_query(['tools' => [$token]]))->assertOk()->assertJsonCount(0, 'tools');
    $this->postJson(executionUrl($token), [])->assertForbidden()->assertSessionMissing('default_tool_executed');
});
