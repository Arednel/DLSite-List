<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductContributorRole;
use App\Enums\ProductField;
use PHPUnit\Framework\TestCase;

class ProductContributorRoleTest extends TestCase
{
    public function test_it_maps_roles_to_product_fields(): void
    {
        $expectedFields = [
            ProductContributorRole::Circle->value => ProductField::Circle,
            ProductContributorRole::Scenario->value => ProductField::Scenario,
            ProductContributorRole::VoiceActor->value => ProductField::VoiceActor,
            ProductContributorRole::Illustration->value => ProductField::Illustration,
            ProductContributorRole::Author->value => ProductField::Author,
        ];

        foreach (ProductContributorRole::cases() as $role) {
            $this->assertSame($expectedFields[$role->value], $role->productField());
        }
    }
}
