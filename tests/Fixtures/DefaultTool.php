<?php

namespace Fosseva\WebMcp\Tests\Fixtures;

use Fosseva\WebMcp\Contracts\WebMcp;
use Laravel\Ai\Tools\Request;

class DefaultTool extends SdkOnlyTool implements WebMcp
{
    public function handle(Request $request): string
    {
        session()->put('default_tool_executed', true);

        return parent::handle($request);
    }
}
