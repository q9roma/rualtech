<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BlogPost;

class FixBlogPosts extends Command
{
    protected $signature = 'blog:fix';
    protected $description = 'Fix blog posts published_at dates';

    public function handle()
    {
        $posts = BlogPost::where('is_published', true)
                        ->whereNull('published_at')
                        ->get();
        
        $this->info('Найдено статей для исправления: ' . $posts->count());
        
        foreach ($posts as $post) {
            $post->published_at = $post->created_at ?? now();
            $post->save();
            $this->line("Исправлена статья: {$post->title}");
        }
        
        $this->info('Готово!');
        
        // Также исправим slug если он кривой
        $badSlugs = BlogPost::where('slug', 'Запись')->get();
        foreach ($badSlugs as $post) {
            $post->slug = \Illuminate\Support\Str::slug($post->title);
            $post->save();
            $this->line("Исправлен slug для: {$post->title}");
        }
        
        return 0;
    }
}
