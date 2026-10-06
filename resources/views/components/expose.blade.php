@props(['tool' => null, 'name' => null])

@php
    if (($tool === null) === ($name === null)) {
        throw new InvalidArgumentException('Provide exactly one tool class or WebMCP name to webmcp::expose.');
    }
    $selection = $tool ?? $name;
    if (! is_string($selection) || $selection === '') {
        throw new InvalidArgumentException('The webmcp::expose component requires a non-empty tool class or WebMCP name.');
    }
    $token = config('webmcp.enabled', true)
        ? app(\Fosseva\WebMcp\PageTools::class)->expose($selection)
        : null;
@endphp

@if ($token !== null)
    <template data-webmcp-tool="{{ $token }}"></template>
    @once
        {!! app(\Fosseva\WebMcp\RuntimeScripts::class)->render() !!}
    @endonce
@endif
