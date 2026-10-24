<?php

use App\Support\OptionStylesheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Filesystem\Filesystem;

return new class extends Migration
{
    public function up(): void
    {
        // Create the generated stylesheet from the currently saved CSS-affecting Options.
        app(OptionStylesheet::class)->regenerate();
    }

    public function down(): void
    {
        // Remove the generated stylesheet introduced by this migration.
        $stylesheet = app(OptionStylesheet::class);
        $files = app(Filesystem::class);
        $path = $stylesheet->path();
        $directory = dirname($path);

        $files->delete($path);

        // Remove the generated directory as well when nothing else uses it.
        if ($files->isDirectory($directory) && $files->files($directory) === [] && $files->directories($directory) === []) {
            $files->deleteDirectory($directory);
        }
    }
};
