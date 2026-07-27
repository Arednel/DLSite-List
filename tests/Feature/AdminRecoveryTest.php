<?php

namespace Tests\Feature;

use App\Models\Option;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_environment_recovery_is_ignored_while_authentication_is_disabled(): void
    {
        User::factory()->create();
        config()->set('auth.admin_password_reset', true);

        $this->get(route('index'))->assertOk();
        $this->get(route('admin.recovery'))->assertRedirect(route('index'));
    }

    public function test_environment_recovery_forces_one_password_reset_then_blocks_until_restart(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'remember_token' => 'old-token',
        ]);
        Option::setUserAuthenticationEnabled(true);
        config()->set('auth.admin_password_reset', true);

        $this->get(route('index'))->assertRedirect(route('admin.recovery'));
        $this->get(route('login'))->assertRedirect(route('admin.recovery'));
        $this->get(route('admin.recovery'))
            ->assertOk()
            ->assertSee('Anyone who can reach this page');

        $this->post(route('admin.recovery.store'), [
            'password' => 'recovered-password',
            'password_confirmation' => 'recovered-password',
        ])->assertRedirect(route('admin.recovery'));

        $user->refresh();
        $this->assertTrue(Hash::check('recovered-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertTrue(Option::adminPasswordResetConsumed());

        $this->get(route('admin.recovery'))
            ->assertOk()
            ->assertSee('Remove ADMIN_PASSWORD_RESET');
        $this->get(route('index'))->assertRedirect(route('admin.recovery'));

        config()->set('auth.admin_password_reset', false);

        $this->get(route('index'))->assertRedirect(route('login'));
        $this->assertFalse(Option::adminPasswordResetConsumed());
        $this->assertDatabaseMissing('options', [
            'key' => Option::ADMIN_PASSWORD_RESET_CONSUMED,
        ]);
    }

    public function test_environment_recovery_rolls_back_password_when_the_consumed_marker_cannot_be_saved(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'remember_token' => 'old-token',
        ]);
        Option::setUserAuthenticationEnabled(true);
        config()->set('auth.admin_password_reset', true);
        $creatingEvent = 'eloquent.creating: ' . Option::class;

        Event::listen($creatingEvent, static function (Option $option): void {
            if ($option->key === Option::ADMIN_PASSWORD_RESET_CONSUMED) {
                throw new RuntimeException('Recovery marker persistence failed.');
            }
        });

        try {
            $this->withoutExceptionHandling()->post(route('admin.recovery.store'), [
                'password' => 'recovered-password',
                'password_confirmation' => 'recovered-password',
            ]);

            $this->fail('Expected recovery marker persistence to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Recovery marker persistence failed.', $exception->getMessage());
        } finally {
            Event::forget($creatingEvent);
        }

        $user->refresh();
        $this->assertTrue(Hash::check('old-password', $user->password));
        $this->assertSame('old-token', $user->remember_token);
        $this->assertFalse(Option::adminPasswordResetConsumed());
    }

    public function test_environment_recovery_refuses_an_unsupported_multiple_user_state(): void
    {
        User::factory()->count(2)->create();
        Option::setUserAuthenticationEnabled(true);
        config()->set('auth.admin_password_reset', true);

        $this->get(route('admin.recovery'))
            ->assertOk()
            ->assertSee('Password recovery requires exactly one administrator account.');

        $this->post(route('admin.recovery.store'), [
            'password' => 'recovered-password',
            'password_confirmation' => 'recovered-password',
        ])->assertSessionHasErrors('password');
    }
}
