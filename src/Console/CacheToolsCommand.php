<?php

namespace Fosseva\WebMcp\Console;

use Fosseva\WebMcp\ToolDiscovery;
use Illuminate\Console\Command;

class CacheToolsCommand extends Command
{
    protected $signature = 'webmcp:cache';

    protected $description = 'Cache discoverable Laravel AI SDK WebMCP tool classes';

    public function handle(ToolDiscovery $discovery): int
    {
        $count = $discovery->cache();
        $this->components->info("Cached {$count} WebMCP tool classes.");

        return self::SUCCESS;
    }
}
