<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BlogPost;

class CheckBlogPosts extends Command
{
    protected $signature = 'blog:check';
    protected $description = 'Check blog posts status';

    public function handle()
    {
        $posts = BlogPost::all();
        
        $this->info('Всего статей: ' . $posts->count());
        
        foreach ($posts as $post) {
            $this->line("ID: {$post->id}");
            $this->line("Название: {$post->title}");
            $this->line("Опубликована: " . ($post->is_published ? 'Да' : 'Нет'));
            $this->line("Дата публикации: " . ($post->published_at ? $post->published_at->format('Y-m-d H:i:s') : 'Не указана'));
            $this->line("Slug: {$post->slug}");
            $this->line("---");
        }
        
        $publishedPosts = BlogPost::published()->get();
        $this->info('Опубликованных статей (с учетом scope): ' . $publishedPosts->count());
        
        return 0;
    }
}
