<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $password = str_repeat('a', 256);

        $this->get(route('index'))->assertRedirect(route('admin.setup'));
        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Create Administrator Account')
            ->assertSee('auth-theme-cherry', false);

        $this->post(route('admin.setup.store'), [
            'username' => 'admin',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('index'));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $user = User::query()->sole();
        $this->assertSame('admin', $user->username);
        $this->assertSame('Admin', $user->user_privilege);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame('argon2id', Hash::info($user->password)['algoName']);

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

        $password = str_repeat('a', 257);

        $this->post(route('admin.setup.store'), [
            'username' => 'admin',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rejects_passwords_longer_than_256_characters(): void
    {
        $this->createAdmin();
        Option::setUserAuthenticationEnabled(true);

        $this->post(route('login.authenticate'), [
            'username' => 'admin',
            'password' => str_repeat('a', 257),
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
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
            ->assertSee('To remove administrator and reset account setup, run:')
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

    public function test_remember_me_cookie_expires_after_180_days_and_authenticates_after_session_loss(): void
    {
        $user = $this->createAdmin();
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

        $this->app['session']->invalidate();
        Auth::forgetGuards();

        $this->withUnencryptedCookie(
            $rememberCookie->getName(),
            $rememberCookie->getValue(),
        )->get(route('index'))->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard('web')->viaRemember());
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

    #[DataProvider('authenticationPagePresentationProvider')]
    public function test_authentication_pages_use_the_selected_theme_and_ui_language(
        string $routeName,
        bool $requiresAdmin,
        bool $recoveryEnabled,
        string $theme,
        string $locale,
        string $title,
        string $content,
    ): void {
        if ($requiresAdmin) {
            $this->createAdmin();
        }

        Option::setUserAuthenticationEnabled(true);
        Option::setAuthenticationPageTheme($theme);
        Option::setUiLanguage($locale);
        config()->set('auth.admin_password_reset', $recoveryEnabled);

        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('<html lang="' . $locale . '">', false)
            ->assertSee('auth-theme-' . $theme, false)
            ->assertSee(trans($title, locale: $locale))
            ->assertSee(trans($content, locale: $locale));
    }

    public static function authenticationPagePresentationProvider(): iterable
    {
        $pages = [
            'login' => [
                'login',
                true,
                false,
                'Sign in to continue',
                'Remember Me',
            ],
            'setup' => [
                'admin.setup',
                false,
                false,
                'Create Administrator Account',
                'Use at least 8 characters.',
            ],
            'help' => [
                'password.help',
                true,
                false,
                'Password Reset Help',
                'Recommended: console command',
            ],
            'recovery' => [
                'admin.recovery',
                true,
                true,
                'Environment Password Recovery',
                'Anyone who can reach this page can set the administrator password while recovery mode is active.',
            ],
        ];

        foreach ($pages as $page => [$routeName, $requiresAdmin, $recoveryEnabled, $title, $content]) {
            foreach (['cherry', 'black'] as $theme) {
                foreach (['en', 'ja'] as $locale) {
                    yield "{$page}-{$theme}-{$locale}" => [
                        $routeName,
                        $requiresAdmin,
                        $recoveryEnabled,
                        $theme,
                        $locale,
                        $title,
                        $content,
                    ];
                }
            }
        }
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'username' => 'admin',
            'password' => 'correct-password',
        ]);
    }
}
