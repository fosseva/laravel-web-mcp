<?php

namespace Fosseva\WebMcp;

use Fosseva\WebMcp\Contracts\WebMcp;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;

class PageTools
{
    /** @var array<string, string> */
    private array $tokens = [];

    public function __construct(private readonly ToolDiscovery $discovery) {}

    public function expose(string $selection): string
    {
        $class = $this->resolveClass($selection);

        return $this->tokens[$class] ??= app(ExposureTokens::class)->issue($class, request());
    }

    private function resolveClass(string $selection): string
    {
        if (is_subclass_of($selection, Tool::class) && is_subclass_of($selection, WebMcp::class)) {
            return $selection;
        }

        $matches = [];
        foreach ($this->discovery->classes() as $class) {
            if (app($class)->webMcpName() === $selection) {
                $matches[$class] = $class;
            }
        }

        if (count($matches) > 1) {
            throw new InvalidArgumentException("Multiple discovered tools use the name [{$selection}].");
        }

        return array_values($matches)[0] ?? throw new InvalidArgumentException("No tool named [{$selection}] was discovered. Pass a tool class or place it in a discovery directory.");
    }
}
