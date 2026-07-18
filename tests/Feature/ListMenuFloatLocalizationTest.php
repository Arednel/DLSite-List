<?php

namespace Tests\Feature;

use App\Enums\UiLanguage;
use App\Models\Option;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListMenuFloatLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_menu_localizes_visible_and_accessible_copy(): void
    {
        $english = $this->get(route('index'))->assertOk();

        $english
            ->assertSee('<html lang="en">', false)
            ->assertSee('aria-label="Open navigation menu"', false)
            ->assertSee('<span class="menu-label">Quick Add</span>', false)
            ->assertSee('<span class="menu-label">All Works</span>', false)
            ->assertSee('<span class="menu-label">R15</span>', false)
            ->assertSee('data-work-form-modal-title="Quick Add"', false);

        Option::setUiLanguage(UiLanguage::Japanese);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('aria-label="ナビゲーションメニューを開く"', false)
            ->assertSee('<span class="menu-label">クイック追加</span>', false)
            ->assertSee('<span class="menu-label">すべての作品</span>', false)
            ->assertSee('<span class="menu-label">R15</span>', false)
            ->assertSee('aria-label="モーダルを閉じる"', false);
    }
}
