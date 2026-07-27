<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_command_updates_the_single_user(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'remember_token' => 'old-token',
        ]);

        $this->artisan('admin:reset-password')
            ->expectsQuestion('New password', 'new-secure-password')
            ->expectsQuestion('Confirm new password', 'new-secure-password')
            ->expectsOutput('Administrator password reset. Existing sessions and remember-me cookies will be invalidated.')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
    }

    public function test_reset_password_command_refuses_zero_or_multiple_users(): void
    {
        $this->artisan('admin:reset-password')
            ->expectsOutput('No administrator account exists.')
            ->assertExitCode(1);

        User::factory()->count(2)->create();

        $this->artisan('admin:reset-password')
            ->expectsOutput('Password reset requires exactly one administrator account. Run admin:reset to clear unsupported user rows.')
            ->assertExitCode(1);
    }

    public function test_full_reset_command_requires_confirmation_and_clears_every_user(): void
    {
        User::factory()->count(2)->create();

        $this->artisan('admin:reset')
            ->expectsConfirmation('Remove all 2 administrator account row(s)?', 'yes')
            ->expectsOutput('Administrator account rows cleared. If authentication is enabled, the next request will open setup.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_full_reset_command_can_be_cancelled(): void
    {
        User::factory()->create();

        $this->artisan('admin:reset')
            ->expectsConfirmation('Remove all 1 administrator account row(s)?', 'no')
            ->expectsOutput('Administrator reset cancelled.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 1);
    }
}
