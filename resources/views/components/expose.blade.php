@props(['tool'])

@php
    if (! is_string($tool) || $tool === '') {
        throw new InvalidArgumentException('The webmcp::expose component requires an SDK tool implementing WebMcp.');
    }
    $token = config('webmcp.enabled', true)
        ? app(\Fosseva\WebMcp\PageTools::class)->expose($tool)
        : null;
@endphp

@if ($token !== null)
    <template data-webmcp-tool="{{ $token }}"></template>
    @once
        {!! app(\Fosseva\WebMcp\RuntimeScripts::class)->render() !!}
    @endonce
@endif
