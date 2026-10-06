<?php

namespace Fosseva\WebMcp\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait ProvidesWebMcpDefaults
{
    public function webMcpName(): string
    {
        return Str::snake(class_basename(static::class));
    }

    /** @return array<string, bool> */
    public function webMcpAnnotations(): array
    {
        return [];
    }

    public function webMcpAuthorize(Request $request): bool
    {
        return false;
    }
}
