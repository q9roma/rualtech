<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeploymentCheck extends Command
{
    protected $signature = 'deploy:check {--after-migrate : Also require schema and staff access}';
    protected $description = 'Fail closed when release prerequisites are missing';

    public function handle(): int
    {
        $checks = [
            'Production application environment' => app()->environment('production'),
            'Debug disabled' => ! config('app.debug'),
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Application key configured' => filled(config('app.key')),
            'Secure HTTP-only session cookies' => config('session.secure') && config('session.http_only'),
            'Explicit trusted proxies' => ! array_intersect(['*', '**'], config('security.trusted_proxies', [])),
            'Exposed credentials rotated' => config('security.secrets_rotated'),
            'Production asset manifest exists' => is_file(public_path('build/manifest.json')),
            'No Vite development marker' => ! is_file(public_path('hot')),
            'SSR configured for React storefront' => ! config('storefront.inertia') || config('inertia.ssr.enabled'),
            'Release identifier recorded' => (bool) preg_match('/^[a-f0-9]{40}$/', config('release.commit', '')),
            'PHP runtime is at least 8.4' => PHP_VERSION_ID >= 80400,
            'Experimental checkout is disabled' => ! config('commerce.enabled'),
        ];
        try {
            DB::select('SELECT 1');
            $checks['Database reachable'] = true;
            if ($this->option('after-migrate')) {
                $checks['Staff schema and at least one administrator'] = Schema::hasColumn('users', 'is_admin')
                    && User::where('is_admin', true)->exists();
            }
        } catch (\Throwable) {
            $checks['Database reachable'] = false;
        }
        foreach ($checks as $label => $passed) {
            $passed ? $this->info('PASS '.$label) : $this->error('FAIL '.$label);
        }
        return in_array(false, array_map(fn ($value) => (bool) $value, $checks), true) ? self::FAILURE : self::SUCCESS;
    }
}
