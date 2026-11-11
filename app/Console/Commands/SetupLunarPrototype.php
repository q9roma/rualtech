<?php

namespace App\Console\Commands;

use App\Commerce\PrototypeSetup;
use Illuminate\Console\Command;

class SetupLunarPrototype extends Command
{
    protected $signature = 'commerce:setup-prototype';
    protected $description = 'Create reference data for the local Lunar prototype (no sample products or users)';

    public function handle(PrototypeSetup $setup): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Prototype setup is restricted to local/testing.');
            return self::FAILURE;
        }
        $setup->run();
        $this->info('Lunar prototype reference data is ready.');
        return self::SUCCESS;
    }
}
