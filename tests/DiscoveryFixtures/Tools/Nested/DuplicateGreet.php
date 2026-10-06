<?php

namespace Fosseva\WebMcp\Tests\DiscoveryFixtures\Tools\Nested;

use Fosseva\WebMcp\Tests\Fixtures\Greet;

class DuplicateGreet extends Greet
{
    public function webMcpName(): string
    {
        return 'greet';
    }
}
