<?php

namespace Fosseva\WebMcp\Contracts;

use Illuminate\Http\Request;

interface WebMcp
{
    public function webMcpName(): string;

    /** @return array<string, bool> */
    public function webMcpAnnotations(): array;

    public function webMcpAuthorize(Request $request): bool;
}
