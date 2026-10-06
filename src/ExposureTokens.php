<?php

namespace Fosseva\WebMcp;

use Fosseva\WebMcp\Contracts\WebMcp;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;

class ExposureTokens
{
    /** @param class-string<Tool&WebMcp> $class */
    private function resolve(string $class): Tool&WebMcp
    {
        return app($class);
    }

    public function issue(string $class, Request $request): string
    {
        if (! is_subclass_of($class, Tool::class) || ! is_subclass_of($class, WebMcp::class)) {
            throw new InvalidArgumentException("[{$class}] must implement the SDK Tool and WebMcp contracts to be exposed in Blade.");
        }

        return Crypt::encryptString(json_encode([
            'class' => $class,
            'session' => hash('sha256', $request->session()->getId()),
            'expires' => now()->addMinutes((int) config('webmcp.exposure_ttl', 60))->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    public function resolveExposure(string $token, Request $request): (Tool&WebMcp)|null
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload) || ! is_string($payload['class'] ?? null)
            || ! is_string($payload['session'] ?? null) || ! is_int($payload['expires'] ?? null)
            || $payload['expires'] <= now()->getTimestamp()
            || ! hash_equals(hash('sha256', $request->session()->getId()), $payload['session'])
            || ! is_subclass_of($payload['class'], Tool::class)
            || ! is_subclass_of($payload['class'], WebMcp::class)) {
            return null;
        }

        return $this->resolve($payload['class']);
    }
}
