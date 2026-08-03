<?php

namespace App\Support;

use App\Enums\ProductContributorRole;
use App\Models\Contributor;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class ProductContributorSync
{
    /**
     * @param  array<string, list<string>>  $namesByRole
     */
    public function sync(Product $product, array $namesByRole, ?string $makerId = null): bool
    {
        return DB::transaction(function () use ($product, $namesByRole, $makerId): bool {
            $changed = false;

            foreach ($namesByRole as $role => $names) {
                $role = ProductContributorRole::tryFrom((string) $role);

                if (! $role) {
                    continue;
                }

                $changed = $this->syncRole(
                    $product,
                    $role,
                    $names,
                    $role === ProductContributorRole::Circle ? $makerId : null,
                ) || $changed;
            }

            return $changed;
        });
    }

    /**
     * @param  list<string>  $names
     */
    public function syncRole(
        Product $product,
        ProductContributorRole|string $role,
        array $names,
        ?string $makerId = null,
    ): bool {
        $role = $role instanceof ProductContributorRole
            ? $role
            : ProductContributorRole::from($role);

        return DB::transaction(function () use ($product, $role, $names, $makerId): bool {
            $contributorIds = Contributor::resolveIdsFromNames($names, $makerId);
            $changes = $product->contributorsForRole($role)->sync($contributorIds);

            if (array_filter($changes) === []) {
                return false;
            }

            $product->touch();

            return true;
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public function namesByRole(Product $product): array
    {
        return $product->contributors()
            ->orderBy('contributors.name')
            ->get([
                'contributors.id',
                'contributors.name',
            ])
            ->groupBy('pivot.role')
            ->map(fn($contributors): array => $contributors->pluck('name')->values()->all())
            ->all();
    }
}
