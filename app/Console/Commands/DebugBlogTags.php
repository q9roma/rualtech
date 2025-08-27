<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BlogPost;

class DebugBlogTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:debug-tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug blog post tags data types';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $post = BlogPost::first();
        
        if (!$post) {
            $this->error('No blog posts found');
            return;
        }

        $this->info("Post: {$post->title}");
        $this->info("Tags type: " . gettype($post->tags));
        $this->info("Tags value: " . json_encode($post->tags));
        $this->info("Tags raw: " . $post->getAttributes()['tags']);
        $this->info("Is array: " . (is_array($post->tags) ? 'Yes' : 'No'));
    }
}
