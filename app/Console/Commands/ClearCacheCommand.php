<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-app {--tags=* : Specific cache tags to clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear application specific cache with optional tags';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tags = $this->option('tags');

        if (empty($tags)) {
            // Очищаем все кэши приложения
            $cacheKeys = [
                'navigation_pages',
                'home_service_categories', 
                'services_categories_with_services',
                'featured_blog_posts'
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            // Очищаем кэши с паттернами
            $patterns = ['related_services_*', 'related_blog_posts_*'];
            foreach ($patterns as $pattern) {
                Cache::forget($pattern);
            }

            $this->info('All application cache cleared successfully!');
        } else {
            foreach ($tags as $tag) {
                Cache::forget($tag);
                $this->info("Cache for tag '{$tag}' cleared!");
            }
        }

        return 0;
    }
}
