<?php

namespace App\Support;

use App\Models\Option;
use Illuminate\Filesystem\Filesystem;

final class OptionStylesheet
{
    private const EMPTY_CSS = "/* No Options CSS overrides. */\n";

    public function __construct(private readonly Filesystem $files) {}

    public function path(): string
    {
        return storage_path('app/public/generated/options.css');
    }

    public function regenerate(): void
    {
        $css = $this->render(Option::indexTableWidth(), Option::indexContentOverflow());

        if ($this->files->exists($this->path()) && $this->files->get($this->path()) === $css) {
            return;
        }

        $this->replace($css);
    }

    private function replace(string $css): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path()));
        $this->files->replace($this->path(), $css);
        clearstatcache(true, $this->path());
    }

    private function render(array $tableWidth, array $contentOverflow): string
    {
        $rules = [];
        $dimensions = [];
        $width = Option::indexTableWidthCssFrom($tableWidth);

        if ($width !== '1024px') {
            $dimensions[] = "--index-table-width: {$width};";
        }

        if ($width === '100%') {
            $dimensions[] = 'overflow-x: clip;';
        }

        if ($tableWidth['side_margin'] !== null) {
            $dimensions[] = "--index-table-side-margin: {$tableWidth['side_margin']};";
        }

        if ($dimensions !== []) {
            $rules[] = ".product-index-layout {\n    " . implode("\n    ", $dimensions) . "\n}";
        }

        if ($tableWidth['side_margin'] !== null) {
            $rules[] = <<<'CSS'
.product-index-layout .list-container {
    width: min(
        calc(var(--index-table-width, 1024px) + 34px),
        calc(100% - var(--index-table-side-margin) - var(--index-table-side-margin))
    );
}

.product-index-layout .cover-block,
.product-index-layout .cover-block .image-container {
    width: min(var(--index-table-width, 1024px), 100%);
}

.product-index-layout .progress-menu-container .progress-menu,
.product-index-layout .list-unit {
    width: min(var(--index-table-width, 1024px), calc(100% - 34px));
}

@media (max-width: 768px) {
    .product-index-layout .list-container,
    .product-index-layout .cover-block,
    .product-index-layout .cover-block .image-container,
    .product-index-layout .progress-menu-container .progress-menu,
    .product-index-layout .list-unit {
        width: 100%;
    }
}
CSS;
        }

        foreach ($contentOverflow as $target => $setting) {
            if ($setting['enabled']) {
                $rules[] = sprintf(
                    ".product-index-layout [data-overflow-target=\"%s\"] {\n    --index-content-overflow-height: %s;\n}",
                    $target,
                    $setting['height'],
                );
            }
        }

        return $rules === []
            ? self::EMPTY_CSS
            : "/* Generated from saved Options. */\n" . implode("\n\n", $rules) . "\n";
    }
}
