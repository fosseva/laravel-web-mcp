<?php

namespace Fosseva\WebMcp\Tests;

use Fosseva\WebMcp\ToolDiscovery;
use Fosseva\WebMcp\WebMcpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        app(ToolDiscovery::class)->clear();
    }

    protected function tearDown(): void
    {
        app(ToolDiscovery::class)->clear();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [WebMcpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('webmcp.discovery', [['path' => __DIR__.'/Fixtures', 'namespace' => 'Fosseva\\WebMcp\\Tests\\Fixtures']]);
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    }
}
