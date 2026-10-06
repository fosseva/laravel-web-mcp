<?php

namespace Fosseva\WebMcp;

use Fosseva\WebMcp\Contracts\WebMcp;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use ReflectionClass;

class ToolDiscovery
{
    public function __construct(private readonly Filesystem $files) {}

    /** @return list<class-string<Tool&WebMcp>> */
    public function classes(): array
    {
        $path = $this->cachePath();
        if (! $this->files->exists($path)) {
            return $this->discover();
        }

        $classes = $this->files->getRequire($path);
        if (! is_array($classes)) {
            throw new InvalidArgumentException('Invalid WebMCP discovery cache. Run webmcp:cache.');
        }

        $validated = [];
        foreach ($classes as $class) {
            if (! is_string($class) || ! is_subclass_of($class, Tool::class) || ! is_subclass_of($class, WebMcp::class)) {
                throw new InvalidArgumentException('Stale WebMCP discovery cache. Run webmcp:cache.');
            }
            $validated[] = $class;
        }

        return $validated;
    }

    /** @return list<class-string<Tool&WebMcp>> */
    public function discover(): array
    {
        $classes = [];
        foreach (config('webmcp.discovery', []) as $location) {
            $directory = $location['path'];
            if (! $this->files->isDirectory($directory)) {
                continue;
            }
            foreach ($this->files->allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen(rtrim($directory, '/\\')) + 1, -4);
                $class = rtrim($location['namespace'], '\\').'\\'.str_replace(['/', '\\'], '\\', $relative);
                if (is_subclass_of($class, Tool::class) && is_subclass_of($class, WebMcp::class)
                    && ! (new ReflectionClass($class))->isAbstract()) {
                    $classes[$class] = $class;
                }
            }
        }
        sort($classes);

        return $classes;
    }

    public function cache(): int
    {
        $classes = $this->discover();
        $path = $this->cachePath();
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->replace($path, '<?php return '.var_export($classes, true).';'.PHP_EOL);

        return count($classes);
    }

    public function clear(): void
    {
        $this->files->delete($this->cachePath());
    }

    public function cachePath(): string
    {
        return app()->bootstrapPath('cache/webmcp-tools.php');
    }
}
