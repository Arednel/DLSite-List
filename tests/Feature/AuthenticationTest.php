<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_disabled_and_cherry_is_the_default_theme(): void
    {
        Option::setAdminPasswordResetConsumed(true);

        $this->assertFalse(Option::userAuthenticationEnabled());
        $this->assertSame(
            Option::AUTHENTICATION_PAGE_THEME_CHERRY,
            Option::authenticationPageTheme(),
        );

        $queries = [];
        $captureQueries = true;

        DB::listen(function (QueryExecuted $query) use (&$captureQueries, &$queries): void {
            if ($captureQueries) {
                $queries[] = $query;
            }
        });

        $this->get(route('index'))->assertOk();
        $captureQueries = false;

        $this->assertTrue(Option::adminPasswordResetConsumed());
        $this->assertFalse(
            collect($queries)->contains(
                fn(QueryExecuted $query): bool => in_array(
                    Option::ADMIN_PASSWORD_RESET_CONSUMED,
                    $query->bindings,
                    true,
                ),
            ),
        );
        $this->get(route('login'))->assertRedirect(route('index'));
    }

    public function test_enabling_authentication_without_a_user_forces_setup_and_creates_one_admin(): void
    {
        Option::setUserAuthenticationEnabled(true);

        $this->get(route('index'))->assertRedirect(route('admin.setup'));
        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Create Administrator Account')
            ->assertSee('auth-theme-cherry', false);

        $this->post(route('admin.setup.store'), [
            'username' => 'admin',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ])->assertRedirect(route('index'));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $user = User::query()->sole();
        $this->assertSame('admin', $user->username);
        $this->assertSame('Admin', $user->user_privilege);
        $this->assertTrue(Hash::check('secure-password', $user->password));

        $this->post(route('admin.setup.store'), [
            'username' => 'second-admin',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertRedirect(route('index'));

        $this->assertDatabaseCount('users', 1);
    }

    public function test_setup_validates_username_and_confirmed_password(): void
    {
        Option::setUserAuthenticationEnabled(true);

        $this->post(route('admin.setup.store'), [
            'username' => '',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['username', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_enabled_authentication_protects_pages_mutations_and_livewire_requests(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);

        $this->get(route('index'))->assertRedirect(route('login'));
        $this->get(route('options.index'))->assertRedirect(route('login'));
        $this->get(route('autocomplete.tags'))->assertRedirect(route('login'));
        $this->post(route('products.store'))->assertRedirect(route('login'));
        $this->post(route('default-livewire.update'))->assertRedirect(route('login'));

        $this->get(route('password.help'))
            ->assertOk()
            ->assertSee('php artisan admin:reset-password')
            ->assertSee('To remove administator and reset account setup, run:')
            ->assertSee('ADMIN_PASSWORD_RESET=true');
    }

    public function test_login_uses_generic_errors_and_limits_each_ip_to_five_failures_for_five_minutes(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.authenticate'), [
                'username' => 'admin',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'username' => 'The provided credentials are incorrect.',
            ]);
        }

        $this->post(route('login.authenticate'), [
            'username' => 'admin',
            'password' => 'correct-password',
        ])->assertSessionHasErrors('username');

        $this->assertGreaterThan(
            0,
            RateLimiter::availableIn('admin-login:192.0.2.10'),
        );

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post(route('login.authenticate'), [
                'username' => 'admin',
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors([
                'username' => 'The provided credentials are incorrect.',
            ]);

        $this->travel(301)->seconds();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login.authenticate'), [
                'username' => 'admin',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('index'));

        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts('admin-login:192.0.2.10'));
    }

    public function test_login_requires_the_exact_username_case(): void
    {
        User::factory()->create([
            'username' => 'Admin',
            'password' => 'correct-password',
        ]);
        Option::setUserAuthenticationEnabled(true);
        $limiterKey = 'admin-login:192.0.2.20';

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post(route('login.authenticate'), [
                'username' => 'admin',
                'password' => 'correct-password',
            ])
            ->assertSessionHasErrors([
                'username' => 'The provided credentials are incorrect.',
            ]);

        $this->assertGuest();
        $this->assertSame(1, RateLimiter::attempts($limiterKey));

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post(route('login.authenticate'), [
                'username' => 'Admin',
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('index'));

        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts($limiterKey));
    }

    public function test_remember_me_cookie_expires_after_180_days(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);
        $startedAt = now();

        $response = $this->post(route('login.authenticate'), [
            'username' => 'admin',
            'password' => 'correct-password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('index'));

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn($cookie): bool => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie);
        $this->assertEqualsWithDelta(
            $startedAt->copy()->addDays(180)->timestamp,
            $rememberCookie->getExpiresTime(),
            5,
        );
    }

    public function test_successful_login_follows_intended_url_and_logout_invalidates_authentication(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);

        $this->get(route('options.index'))->assertRedirect(route('login'));

        $this->post(route('login.authenticate'), [
            'username' => 'admin',
            'password' => 'correct-password',
        ])->assertRedirect(route('options.index'));

        $this->assertAuthenticated();
        $this->get(route('index'))
            ->assertOk()
            ->assertDontSee('action="' . route('logout') . '"', false);
        $this->get(route('options.index', ['tab' => 'authentication']))
            ->assertOk()
            ->assertSee('action="' . route('logout') . '"', false)
            ->assertSee('Logout');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_black_authentication_theme_is_applied_to_login_and_help(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);
        Option::setAuthenticationPageTheme(Option::AUTHENTICATION_PAGE_THEME_BLACK);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('auth-theme-black', false)
            ->assertSee('Remember Me')
            ->assertSee('Forgot your password?');

        $this->get(route('password.help'))
            ->assertOk()
            ->assertSee('auth-theme-black', false);
    }

    public function test_authentication_pages_follow_the_saved_japanese_ui_language(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);
        Option::setUiLanguage('ja');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="ja">', false);

        $this->get(route('password.help'))
            ->assertOk()
            ->assertSee('パスワードリセットのヘルプ');
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'username' => 'admin',
            'password' => 'correct-password',
        ]);
    }
}
