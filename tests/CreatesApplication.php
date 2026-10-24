<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        static $testingStorageInitialized = false;

        $testingStoragePath = dirname(__DIR__) . '/storage/framework/testing/application';

        if (! $testingStorageInitialized) {
            $files = new Filesystem;
            $files->deleteDirectory($testingStoragePath);
            $files->ensureDirectoryExists($testingStoragePath . '/framework/views');

            register_shutdown_function(static function () use ($testingStoragePath): void {
                (new Filesystem)->deleteDirectory($testingStoragePath);
            });

            $testingStorageInitialized = true;
        }

        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->useStoragePath($testingStoragePath);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
