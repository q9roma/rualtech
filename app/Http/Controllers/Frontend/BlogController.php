<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::published()
            ->orderBy('published_at', 'desc')
            ->paginate(9);

        // Кэшируем рекомендуемые записи на 1 час
        $featuredPosts = Cache::remember('featured_blog_posts', 3600, function () {
            return BlogPost::published()
                ->orderBy('published_at', 'desc')
                ->limit(3)
                ->get();
        });

        return view('frontend.blog.index', compact('posts', 'featuredPosts'));
    }

    public function show(string $slug): View
    {
        $post = BlogPost::where('slug', $slug)
            ->published()
            ->firstOrFail();

        // Кэшируем похожие записи на 2 часа
        $relatedPosts = Cache::remember("related_blog_posts_{$post->id}", 7200, function () use ($post) {
            return BlogPost::published()
                ->where('id', '!=', $post->id)
                ->inRandomOrder()
                ->limit(3)
                ->get();
        });

        return view('frontend.blog.show', compact('post', 'relatedPosts'));
    }
}
