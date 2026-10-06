<?php

namespace Fosseva\WebMcp;

use Fosseva\WebMcp\Contracts\WebMcp;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use LogicException;

class BrowserToolDefinition
{
    /** @return array<string, mixed> */
    public function make(Tool&WebMcp $tool, string $token): array
    {
        $name = $tool->webMcpName();
        if (! preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $name)) {
            throw new LogicException('WebMCP tool names must contain 1–128 letters, numbers, underscores, dots, or hyphens.');
        }

        $schema = JsonSchema::object($tool->schema(new JsonSchemaTypeFactory))->toArray();
        if (($schema['properties'] ?? null) === []) {
            $schema['properties'] = (object) [];
        }

        return [
            'name' => $name,
            'description' => (string) $tool->description(),
            'inputSchema' => $schema,
            'annotations' => (object) $tool->webMcpAnnotations(),
            'execution' => ['method' => 'POST', 'url' => route('webmcp.execute', ['tool' => $token])],
        ];
    }
}
