<?php

use Fosseva\WebMcp\PageTools;
use Fosseva\WebMcp\Tests\DiscoveryFixtures\Tools\Nested\AbstractTool;
use Fosseva\WebMcp\Tests\DiscoveryFixtures\Tools\Nested\DuplicateGreet;
use Fosseva\WebMcp\Tests\DiscoveryFixtures\Tools\RequestNamedTool;
use Fosseva\WebMcp\Tests\Fixtures\Greet;
use Fosseva\WebMcp\Tests\Fixtures\SdkOnlyTool;
use Fosseva\WebMcp\ToolDiscovery;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/fixture', function () {
        return Blade::render('<x-webmcp::expose :tool="$class" />', ['class' => Greet::class]);
    });
});

function additionalDiscoveryLocation(): array
{
    return ['path' => __DIR__.'/DiscoveryFixtures/Tools', 'namespace' => 'Fosseva\\WebMcp\\Tests\\DiscoveryFixtures\\Tools'];
}

test('discovery scans nested directories and ignores abstract and non opted in tools', function () {
    config(['webmcp.discovery' => [...config('webmcp.discovery'), additionalDiscoveryLocation()]]);
    $classes = app(ToolDiscovery::class)->discover();
    expect($classes)->toContain(Greet::class, DuplicateGreet::class, RequestNamedTool::class)
        ->not->toContain(AbstractTool::class, SdkOnlyTool::class);
});

test('duplicate discovered names fail instead of choosing a tool', function () {
    config(['webmcp.discovery' => [...config('webmcp.discovery'), additionalDiscoveryLocation()]]);
    expect(fn () => app(PageTools::class)->expose('greet'))->toThrow(InvalidArgumentException::class, 'Multiple discovered tools');
});

test('optimize builds discovery cache and optimize clear removes it', function () {
    $discovery = app(ToolDiscovery::class);
    $this->artisan('optimize', ['--except' => 'config,events,routes,views'])->assertSuccessful();
    expect(is_file($discovery->cachePath()))->toBeTrue();
    expect($discovery->classes())->toBe($discovery->discover());
    $this->artisan('optimize:clear', ['--except' => 'config,cache,compiled,events,routes,views'])->assertSuccessful();
    expect(is_file($discovery->cachePath()))->toBeFalse();
});

test('cache contains only classes and keeps request dependent names dynamic', function () {
    config(['webmcp.discovery' => [additionalDiscoveryLocation()]]);
    RequestNamedTool::$resolutions = 0;
    $this->artisan('webmcp:cache')->assertSuccessful();
    expect(RequestNamedTool::$resolutions)->toBe(0);
    Route::middleware('web')->get('/dynamic/{name}', function (string $name) {
        return Blade::render('<x-webmcp::expose :name="$name" />', compact('name'));
    });
    $this->withHeader('X-Tool-Name', 'first_name')->get('/dynamic/first_name')->assertOk()->assertSee('data-webmcp-tool');
    $this->withHeader('X-Tool-Name', 'second_name')->get('/dynamic/second_name')->assertOk()->assertSee('data-webmcp-tool');
    expect(file_get_contents(app(ToolDiscovery::class)->cachePath()))->not->toContain('first_name', 'second_name');
    $this->artisan('webmcp:clear')->assertSuccessful();
});

test('authorization is rechecked when discovery is cached', function () {
    $this->artisan('webmcp:cache')->assertSuccessful();
    $this->withSession(['allowed' => true]);
    $token = exposureToken($this);
    $this->withSession(['allowed' => false]);
    session()->save();
    $this->postJson(executionUrl($token), ['name' => 'Aniket'])->assertForbidden()->assertSessionMissing('greeted');
});

test('uncached discovery handles missing directories and class exposure remains available', function () {
    config(['webmcp.discovery' => [['path' => __DIR__.'/missing', 'namespace' => 'Missing\\Tools']]]);
    expect(app(ToolDiscovery::class)->classes())->toBe([]);
    $this->withSession(['allowed' => true]);
    exposureToken($this);
});
