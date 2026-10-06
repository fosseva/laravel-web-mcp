<?php

namespace Fosseva\WebMcp\Http\Controllers;

use Fosseva\WebMcp\BrowserToolDefinition;
use Fosseva\WebMcp\ExposureTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class ManifestController
{
    public function __construct(private readonly ExposureTokens $tools, private readonly BrowserToolDefinition $descriptors) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        $selected = $request->query('tools', []);
        abort_unless(is_array($selected) && count($selected) <= 50, 422, 'Select at most 50 Blade tools.');
        $definitions = [];
        foreach (array_unique(array_filter($selected, is_string(...))) as $token) {
            $tool = $this->tools->resolveExposure($token, $request);
            if ($tool === null || ! $tool->webMcpAuthorize($request)) {
                continue;
            }
            $descriptor = $this->descriptors->make($tool, $token);
            if (isset($definitions[$descriptor['name']])) {
                throw new LogicException("Duplicate WebMCP tool name [{$descriptor['name']}].");
            }
            $definitions[$descriptor['name']] = $descriptor;
        }

        $tools = array_values($definitions);
        // Include the CSRF token so a rotated token never receives an obsolete 304.
        $revision = hash('sha256', json_encode([$tools, csrf_token()], JSON_THROW_ON_ERROR));
        $etag = '"'.$revision.'"';
        $headers = ['Cache-Control' => 'private, no-cache', 'ETag' => $etag, 'Vary' => 'Cookie, Authorization, Accept-Language'];
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response()->json(['revision' => $revision, 'csrfToken' => csrf_token(), 'tools' => $tools], 200, $headers);
    }
}
