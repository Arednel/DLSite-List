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
    public function sync(Product $product, array $namesByRole, ?string $makerId = null): void
    {
        DB::transaction(function () use ($product, $namesByRole, $makerId): void {
            foreach ($namesByRole as $role => $names) {
                $role = ProductContributorRole::tryFrom((string) $role);

                if (! $role) {
                    continue;
                }

                $this->syncRole(
                    $product,
                    $role,
                    $names,
                    $role === ProductContributorRole::Circle ? $makerId : null,
                );
            }
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
    ): void {
        $role = $role instanceof ProductContributorRole
            ? $role
            : ProductContributorRole::from($role);

        DB::transaction(function () use ($product, $role, $names, $makerId): void {
            $contributorIds = Contributor::resolveIdsFromNames($names, $makerId);

            $product->contributorsForRole($role)
                ->syncWithPivotValues($contributorIds, ['role' => $role->value]);
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
