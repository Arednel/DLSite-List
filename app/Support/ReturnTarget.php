<?php

namespace App\Support;

use Illuminate\Http\Request;

final readonly class ReturnTarget
{
    private const INDEX_ROUTE = 'index';

    private const ALLOWED_ROUTES = [
        self::INDEX_ROUTE,
        'tags.index',
    ];

    public function __construct(
        public string $route,
        public array $query = [],
        public ?string $fragment = null,
    ) {
    }

    public static function fromRequest(Request $request, ?string $defaultFragment = null): self
    {
        $route = $request->string('return_route')->trim()->toString();
        $route = in_array($route, self::ALLOWED_ROUTES, true)
            ? $route
            : self::INDEX_ROUTE;

        return new self(
            route: $route,
            query: $route === self::INDEX_ROUTE
                ? self::normalizeQuery($request->input('return_query', []))
                : [],
            fragment: $route === self::INDEX_ROUTE
                ? self::normalizeFragment($request->input('return_fragment', $defaultFragment))
                : null,
        );
    }

    public function withFragment(?string $fragment): self
    {
        if ($this->route !== self::INDEX_ROUTE) {
            return $this;
        }

        return new self(
            route: $this->route,
            query: $this->query,
            fragment: self::normalizeFragment($fragment),
        );
    }

    public function withIndexProgress(?string $progress): self
    {
        if ($this->route !== self::INDEX_ROUTE) {
            return $this;
        }

        $query = $this->query;
        unset($query['progress']);

        if (filled($progress)) {
            $query['progress'] = $progress;
        }

        return new self(
            route: $this->route,
            query: $query,
            fragment: $this->fragment,
        );
    }

    public function toUrl(): string
    {
        $url = route(
            $this->route,
            $this->route === self::INDEX_ROUTE ? $this->query : [],
            false,
        );

        if ($this->fragment !== null) {
            $url .= '#' . rawurlencode($this->fragment);
        }

        return $url;
    }

    private static function normalizeQuery(mixed $query): array
    {
        return is_array($query)
            ? ProductIndexFilters::fromQuery($query)->toQuery()
            : [];
    }

    private static function normalizeFragment(mixed $fragment): ?string
    {
        if (!is_scalar($fragment)) {
            return null;
        }

        $fragment = trim((string) $fragment);

        return blank($fragment) ? null : $fragment;
    }
}
