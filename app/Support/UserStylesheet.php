<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

final class UserStylesheet
{
    public function __construct(private readonly Filesystem $files) {}

    public function path(): string
    {
        return storage_path('app/public/generated/user.css');
    }

    public function contents(): string
    {
        return $this->files->exists($this->path())
            ? $this->files->get($this->path())
            : '';
    }

    public function save(string $css): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path()));
        $this->files->replace($this->path(), $css);
        clearstatcache(true, $this->path());
    }

    public function ensureExists(): void
    {
        if (! $this->files->exists($this->path())) {
            $this->save('');
        }
    }
}
