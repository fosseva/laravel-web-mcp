<?php

namespace Fosseva\WebMcp\Http\Controllers;

use Fosseva\WebMcp\ExposureTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request as ToolRequest;

class ExecuteToolController
{
    public function __construct(private readonly ExposureTokens $tools) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->query('tool');
        $tool = is_string($token) ? $this->tools->resolveExposure($token, $request) : null;
        abort_if($tool === null, 404, 'Blade tool exposure is invalid or expired. Reload the page.');
        abort_unless($tool->webMcpAuthorize($request), 403, 'This tool is not authorized.');

        $arguments = new ToolRequest($request->json()->all());
        if ($tool instanceof Approvable && $tool->shouldRequestApproval($arguments) !== null) {
            abort(409, 'This tool requires SDK approval. Browser approval is not supported.');
        }

        return response()->json(['result' => (string) $tool->handle($arguments)]);
    }
}
