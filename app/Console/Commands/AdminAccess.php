<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AdminAccess extends Command
{
    protected $signature = 'admin:access {email} {--revoke : Remove staff access}';
    protected $description = 'Explicitly grant or revoke staff access for an existing account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }
        $user->is_admin = ! $this->option('revoke');
        $user->save();
        Log::notice('Staff access changed from CLI', ['user_id' => $user->id, 'is_admin' => $user->is_admin]);
        $this->info($user->is_admin ? 'Staff access granted.' : 'Staff access revoked.');
        return self::SUCCESS;
    }
}
