<?php

namespace Fosseva\WebMcp\Console;

use Fosseva\WebMcp\ToolDiscovery;
use Illuminate\Console\Command;

class ClearToolsCommand extends Command
{
    protected $signature = 'webmcp:clear';

    protected $description = 'Clear the WebMCP tool discovery cache';

    public function handle(ToolDiscovery $discovery): int
    {
        $discovery->clear();
        $this->components->info('WebMCP tool discovery cache cleared.');

        return self::SUCCESS;
    }
}
