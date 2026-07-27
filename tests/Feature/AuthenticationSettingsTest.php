<?php

namespace Tests\Feature;

use App\Livewire\AuthenticationSettings;
use App\Livewire\OptionsResetDefaults;
use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_has_a_separate_options_tab_without_reset_all(): void
    {
        $this->get(route('options.index', ['tab' => 'authentication']))
            ->assertOk()
            ->assertSee('href="/options?tab=authentication"', false)
            ->assertSeeLivewire(AuthenticationSettings::class)
            ->assertDontSeeLivewire(OptionsResetDefaults::class)
            ->assertDontSee('action="' . route('logout') . '"', false);
    }

    public function test_settings_save_theme_and_redirect_to_setup_when_enabling_without_an_account(): void
    {
        Livewire::test(AuthenticationSettings::class)
            ->set('enabled', true)
            ->set('theme', Option::AUTHENTICATION_PAGE_THEME_BLACK)
            ->call('saveSettings')
            ->assertRedirect(route('admin.setup'));

        $this->assertTrue(Option::userAuthenticationEnabled());
        $this->assertSame(
            Option::AUTHENTICATION_PAGE_THEME_BLACK,
            Option::authenticationPageTheme(),
        );
    }

    public function test_enabling_with_an_existing_account_redirects_a_guest_to_login(): void
    {
        User::factory()->create();

        Livewire::test(AuthenticationSettings::class)
            ->set('enabled', true)
            ->call('saveSettings')
            ->assertRedirect(route('login'));
    }

    public function test_authentication_options_are_not_changed_by_global_reset(): void
    {
        Option::setUserAuthenticationEnabled(true);
        Option::setAuthenticationPageTheme(Option::AUTHENTICATION_PAGE_THEME_BLACK);

        Option::resetVisibleSettingsToDefault();

        $this->assertTrue(Option::userAuthenticationEnabled());
        $this->assertSame(
            Option::AUTHENTICATION_PAGE_THEME_BLACK,
            Option::authenticationPageTheme(),
        );
    }

    public function test_authenticated_admin_can_change_password_and_is_logged_out(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'remember_token' => 'old-token',
        ]);
        Option::setUserAuthenticationEnabled(true);
        $this->actingAs($user);

        Livewire::test(AuthenticationSettings::class)
            ->set('newPassword', 'new-secure-password')
            ->set('newPasswordConfirmation', 'new-secure-password')
            ->call('changePassword')
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertGuest();
    }

    public function test_password_change_requires_matching_confirmed_values(): void
    {
        $user = User::factory()->create();
        Option::setUserAuthenticationEnabled(true);

        Livewire::actingAs($user)
            ->test(AuthenticationSettings::class)
            ->set('newPassword', 'new-secure-password')
            ->set('newPasswordConfirmation', 'different-password')
            ->call('changePassword')
            ->assertHasErrors(['newPassword']);
    }
}
