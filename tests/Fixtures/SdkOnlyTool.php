<?php

namespace Fosseva\WebMcp\Tests\Fixtures;

use Fosseva\WebMcp\Concerns\ProvidesWebMcpDefaults;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SdkOnlyTool implements Tool
{
    use ProvidesWebMcpDefaults;

    public function description(): string
    {
        return 'An SDK tool that has not opted into browser exposure.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return 'SDK only';
    }
}
