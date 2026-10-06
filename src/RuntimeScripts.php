<?php

namespace Fosseva\WebMcp;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

class RuntimeScripts
{
    public function __construct(private readonly UrlGenerator $url) {}

    public function render(): HtmlString
    {
        if (! config('webmcp.enabled', true)) {
            return new HtmlString('');
        }

        $json = json_encode([
            'manifestUrl' => $this->url->route('webmcp.manifest'),
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $runtime = e($this->url->route('webmcp.runtime'));

        return new HtmlString(<<<HTML
<script type="application/json" id="webmcp-config">{$json}</script>
<script src="{$runtime}" defer data-webmcp-runtime></script>
HTML);
    }
}
