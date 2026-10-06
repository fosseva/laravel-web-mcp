<?php

namespace Fosseva\WebMcp\Tests\Fixtures;

use Fosseva\WebMcp\Concerns\ProvidesWebMcpDefaults;
use Fosseva\WebMcp\Contracts\WebMcp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;

class Greet implements Tool, WebMcp
{
    use ProvidesWebMcpDefaults;

    public function description(): string
    {
        return 'Greet a person using the existing SDK handler.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['name' => $schema->string()->max(80)->required()];
    }

    public function webMcpAuthorize(Request $request): bool
    {
        return $request->session()->get('allowed', false);
    }

    public function handle(ToolRequest $request): string
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        session()->put('greeted', $data['name']);

        return 'Hello, '.$data['name'].'!';
    }
}
