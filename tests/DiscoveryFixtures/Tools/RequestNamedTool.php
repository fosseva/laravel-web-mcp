<?php

namespace Fosseva\WebMcp\Tests\DiscoveryFixtures\Tools;

use Fosseva\WebMcp\Tests\Fixtures\Greet;

class RequestNamedTool extends Greet
{
    public static int $resolutions = 0;

    public function __construct()
    {
        self::$resolutions++;
    }

    public function webMcpName(): string
    {
        return request()->header('X-Tool-Name', 'request_greeting');
    }
}
