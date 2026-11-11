<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AdminPassword extends Command
{
    protected $signature = 'admin:password {email}';
    protected $description = 'Rotate an existing account password through hidden prompts';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! $this->input->isInteractive()) {
            $this->error('Existing user and interactive terminal required.');
            return self::FAILURE;
        }
        $password = $this->secret('New password (at least 12 characters)');
        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails() || $password !== $this->secret('Repeat password')) {
            $this->error('Password requirements or confirmation did not match.');
            return self::FAILURE;
        }
        $user->password = $password;
        $user->remember_token = null;
        $user->save();
        Log::notice('Account password rotated from CLI', ['user_id' => $user->id]);
        $this->info('Password updated; remember token revoked.');
        return self::SUCCESS;
    }
}
