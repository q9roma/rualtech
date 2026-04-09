<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    private const CACHE_TTL_SECONDS = 3600;

    private const RESERVED_PAGE_SLUGS = [
        'admin',
        'login',
        'catalog',
        'services',
        'blog',
        'contact',
        'test-login',
    ];

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /login',
            'Disallow: /test-login',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml.v1', self::CACHE_TTL_SECONDS, fn () => $this->buildSitemapXml());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function buildSitemapXml(): string
    {
        $urls = [];

        $add = function (
            string $loc,
            ?string $lastmod = null,
            string $changefreq = 'weekly',
            string $priority = '0.5',
        ) use (&$urls): void {
            $urls[] = [
                'loc' => $loc,
                'lastmod' => $lastmod,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        };

        $add(route('home'), now()->toAtomString(), 'weekly', '1.0');
        $add(route('products.index'), null, 'daily', '0.9');
        $add(route('services.index'), null, 'weekly', '0.8');
        $add(route('blog.index'), null, 'weekly', '0.8');

        foreach (ServiceCategory::query()->where('is_active', true)->orderBy('id')->get() as $category) {
            $add(
                route('services.category', ['categorySlug' => $category->slug]),
                $category->updated_at?->toAtomString(),
                'weekly',
                '0.7',
            );
        }

        Service::query()
            ->where('is_active', true)
            ->with(['category' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('id')
            ->chunk(500, function ($services) use ($add): void {
                foreach ($services as $service) {
                    $category = $service->category;
                    if ($category === null) {
                        continue;
                    }
                    $add(
                        route('services.show', [
                            'categorySlug' => $category->slug,
                            'serviceSlug' => $service->slug,
                        ]),
                        $service->updated_at?->toAtomString(),
                        'weekly',
                        '0.6',
                    );
                }
            });

        BlogPost::query()
            ->published()
            ->orderBy('id')
            ->chunk(500, function ($posts) use ($add): void {
                foreach ($posts as $post) {
                    $add(
                        route('blog.show', $post->slug),
                        $post->updated_at?->toAtomString(),
                        'monthly',
                        '0.6',
                    );
                }
            });

        Product::query()
            ->active()
            ->orderBy('id')
            ->chunk(500, function ($products) use ($add): void {
                foreach ($products as $product) {
                    $add(
                        route('products.show', $product),
                        $product->updated_at?->toAtomString(),
                        'weekly',
                        '0.7',
                    );
                }
            });

        if (Schema::hasTable('pages')) {
            foreach (
                Page::query()
                    ->where('is_active', true)
                    ->whereNotIn('slug', self::RESERVED_PAGE_SLUGS)
                    ->orderBy('id')
                    ->get() as $page
            ) {
                $add(
                    route('pages.show', $page->slug),
                    $page->updated_at?->toAtomString(),
                    'monthly',
                    '0.5',
                );
            }
        }

        return $this->renderUrlset($urls);
    }

    /**
     * @param  array<int, array{loc: string, lastmod: ?string, changefreq: string, priority: string}>  $urls
     */
    private function renderUrlset(array $urls): string
    {
        $parts = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $u) {
            $parts[] = '<url>';
            $parts[] = '<loc>'.htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
            if ($u['lastmod'] !== null && $u['lastmod'] !== '') {
                $parts[] = '<lastmod>'.htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</lastmod>';
            }
            $parts[] = '<changefreq>'.htmlspecialchars($u['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</changefreq>';
            $parts[] = '<priority>'.htmlspecialchars($u['priority'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</priority>';
            $parts[] = '</url>';
        }

        $parts[] = '</urlset>';

        return implode('', $parts);
    }
}
