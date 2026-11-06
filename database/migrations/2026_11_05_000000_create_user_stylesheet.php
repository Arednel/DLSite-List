<?php

use App\Support\UserStylesheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Filesystem\Filesystem;

return new class extends Migration
{
    public function up(): void
    {
        // Create the stylesheet so Blade filemtime() calls are always valid.
        app(UserStylesheet::class)->save('');
    }

    public function down(): void
    {
        $stylesheet = app(UserStylesheet::class);
        app(Filesystem::class)->delete($stylesheet->path());
    }
};
