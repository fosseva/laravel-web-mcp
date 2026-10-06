<?php

namespace Fosseva\WebMcp\Tests\Fixtures;

class NamedGreet extends Greet
{
    public function webMcpName(): string
    {
        return 'welcome';
    }
}
