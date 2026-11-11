<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {name} {email}';
    protected $description = 'Create a staff account using a hidden password prompt';

    public function handle(): int
    {
        $data = ['name' => $this->argument('name'), 'email' => $this->argument('email')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());
            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively. Passwords must not be passed through command arguments.');
            return self::FAILURE;
        }
        $password = $this->secret('Password (at least 12 characters)');
        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails() || $password !== $this->secret('Repeat password')) {
            $this->error('Password requirements or confirmation did not match.');
            return self::FAILURE;
        }
        $user = new User($data);
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Staff account created.');
        return self::SUCCESS;
    }
}
