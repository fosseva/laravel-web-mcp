<?php

namespace Fosseva\WebMcp\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class RuntimeController
{
    public function __invoke(): Response
    {
        return response(File::get(__DIR__.'/../../../resources/js/webmcp.js'), 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
        ]);
    }
}
