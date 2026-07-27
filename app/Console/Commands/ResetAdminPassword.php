<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:reset-password';

    protected $description = 'Reset the password for the single administrator account';

    public function handle(): int
    {
        $userCount = User::query()->count();

        if ($userCount !== 1) {
            $this->error(
                $userCount === 0
                    ? 'No administrator account exists.'
                    : 'Password reset requires exactly one administrator account. Run admin:reset to clear unsupported user rows.',
            );

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $confirmation = (string) $this->secret('Confirm new password');

        $validator = Validator::make(
            [
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->sole()->replacePassword($password);

        $this->info('Administrator password reset. Existing sessions and remember-me cookies will be invalidated.');

        return self::SUCCESS;
    }
}
