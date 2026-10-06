<?php

namespace Fosseva\WebMcp;

class PageTools
{
    /** @var array<string, string> */
    private array $tokens = [];

    public function expose(string $class): string
    {
        return $this->tokens[$class] ??= app(ExposureTokens::class)->issue($class, request());
    }
}
