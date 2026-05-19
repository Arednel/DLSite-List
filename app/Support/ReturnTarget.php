<?php

namespace App\Support;

use App\Models\Option;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Uri;

final readonly class ReturnTarget
{
    public function __construct(
        public array $query = [],
        public ?string $fragment = null,
    ) {}

    public static function fromRequest(Request $request, ?string $defaultFragment = null): self
    {
        return new self(
            query: self::normalizeQuery($request->input('return_query', [])),
            fragment: self::normalizeFragment($request->input('return_fragment', $defaultFragment)),
        );
    }

    public function withIndexProgress(?string $progress): self
    {
        $query = $this->queryWithoutPage();

        unset($query['progress']);

        if (filled($progress)) {
            $query['progress'] = $progress;
        }

        return new self(
            query: self::normalizeQuery($query),
            fragment: $this->fragment,
        );
    }

    public function forProduct(
        Product $product,
        ?ProductIndexResults $results = null,
        int|string|null $perPage = null,
        bool $visibilityMayHaveChanged = true,
    ): self {
        $results ??= app(ProductIndexResults::class);
        $perPage ??= Option::indexPerPage();

        $query = $this->queryWithoutPage();
        $savedPage = self::normalizePage($this->query['page'] ?? null) ?? 1;
        $filters = ProductIndexFilters::fromQuery($query);

        if ($results->pageContainsProduct($filters, $product, $perPage, $savedPage)) {
            if ($savedPage > 1 && $perPage !== Option::INDEX_PER_PAGE_UNLIMITED) {
                $query['page'] = (string) $savedPage;
            }

            return new self(
                query: self::normalizeQuery($query),
                fragment: (string) $product->getKey(),
            );
        }

        if (! $visibilityMayHaveChanged) {
            $page = $results->pageForProduct($filters, $product, $perPage);

            if ($page !== null) {
                if ($page > 1) {
                    $query['page'] = (string) $page;
                }

                return new self(
                    query: self::normalizeQuery($query),
                    fragment: (string) $product->getKey(),
                );
            }
        }

        $query = $this->queryForVisibleProduct($product, $results);
        $page = $results->pageForProduct(ProductIndexFilters::fromQuery($query), $product, $perPage);

        if ($page !== null && $page > 1) {
            $query['page'] = (string) $page;
        }

        return new self(
            query: $query,
            fragment: (string) $product->getKey(),
        );
    }

    public function afterDeleting(
        ?ProductIndexResults $results = null,
        int|string|null $perPage = null,
    ): self {
        $results ??= app(ProductIndexResults::class);
        $perPage ??= Option::indexPerPage();

        $savedPage = self::normalizePage($this->query['page'] ?? null);
        $query = $this->queryWithoutPage();

        if ($savedPage === null || $perPage === Option::INDEX_PER_PAGE_UNLIMITED) {
            return new self(query: $query);
        }

        $lastPage = $results->lastPage(ProductIndexFilters::fromQuery($query), $perPage);
        $page = min($savedPage, $lastPage ?? 1);

        if ($page > 1) {
            $query['page'] = (string) $page;
        }

        return new self(query: $query);
    }

    public function toUrl(): string
    {
        $uri = Uri::route('index', $this->query, false);

        if ($this->fragment !== null) {
            $uri = $uri->withFragment($this->fragment);
        }

        return $uri->value();
    }

    private static function normalizeQuery(mixed $query): array
    {
        if (! is_array($query)) {
            return [];
        }

        $normalizedQuery = ProductIndexFilters::fromQuery($query)->toQuery();
        $page = self::normalizePage($query['page'] ?? null);

        if ($page !== null && $page > 1) {
            $normalizedQuery['page'] = (string) $page;
        }

        return $normalizedQuery;
    }

    private static function normalizeFragment(mixed $fragment): ?string
    {
        if (! is_scalar($fragment)) {
            return null;
        }

        $fragment = trim((string) $fragment);

        return blank($fragment) ? null : $fragment;
    }

    private static function normalizePage(mixed $value): ?int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $page === false ? null : $page;
    }

    private function queryWithoutPage(): array
    {
        return Arr::except($this->query, 'page');
    }

    private function queryForVisibleProduct(Product $product, ProductIndexResults $results): array
    {
        $query = $this->queryWithoutPage();

        if (
            ! self::hasVisibilityFilters($query)
            || $results->containsProduct(ProductIndexFilters::fromQuery($query), $product)
        ) {
            return ProductIndexFilters::fromQuery($query)->toQuery();
        }

        foreach (ProductIndexFilters::VISIBILITY_FILTER_GROUPS as $filterGroup) {
            if (! Arr::hasAny($query, $filterGroup)) {
                continue;
            }

            $groupQuery = Arr::only($query, $filterGroup);

            if (! $results->containsProduct(ProductIndexFilters::fromQuery($groupQuery), $product)) {
                $query = Arr::except($query, $filterGroup);
            }
        }

        return ProductIndexFilters::fromQuery($query)->toQuery();
    }

    private static function hasVisibilityFilters(array $query): bool
    {
        foreach (ProductIndexFilters::VISIBILITY_FILTER_GROUPS as $filterGroup) {
            if (Arr::hasAny($query, $filterGroup)) {
                return true;
            }
        }

        return false;
    }
}
