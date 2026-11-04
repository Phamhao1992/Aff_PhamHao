<?php

namespace App\Support;

use App\Models\Blog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MagazineLayout
{
    public static function usesMagazineChrome(): bool
    {
        if (! request()->routeIs('landing.show')) {
            return true;
        }

        $template = (string) request()->attributes->get('landing_template', 'template1');

        return in_array($template, ['template1', 'template2', 'template3', 'template4'], true);
    }

    /** Chuẩn hóa nhãn menu (vd: Review cũ → Blog). */
    public static function navLabel(string $label): string
    {
        $label = trim($label);

        if (strcasecmp($label, 'Review') === 0 || strcasecmp($label, 'Review Blog') === 0) {
            return 'Blog';
        }

        return $label !== '' ? $label : 'Link';
    }

    /** Danh mục blog cho menu dropdown — cache 1 giờ. */
    public static function blogNavCategories(): \Illuminate\Support\Collection
    {
        $categories = Cache::remember('magazine.blog_nav_categories', 3600, function () {
            $fromDb = Blog::query()
                ->where('is_published', true)
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');

            if ($fromDb->isNotEmpty()) {
                return $fromDb->values()->all();
            }

            return collect(config('default_categories.names', []))->take(14)->values()->all();
        });

        return collect($categories);
    }

    /** Bài viết gallery footer — cache 15 phút. */
    public static function footerGalleryPosts(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('magazine.footer_gallery_posts', 900, function () {
            return Blog::query()
                ->where('is_published', true)
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();
        });
    }

    /** Bài xem nhiều footer — cache 15 phút. */
    public static function footerRecentViewedPosts(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember('magazine.footer_recent_posts', 900, function () {
            return Blog::query()
                ->where('is_published', true)
                ->orderByDesc('views_count')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();
        });
    }

    /**
     * Breadcrumb cho thanh nav đen: [{label, url?}, ...] — mục cuối không có url.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    public static function breadcrumbTrail(): array
    {
        $home = ['label' => 'Home', 'url' => route('home')];

        if (request()->routeIs('home')) {
            return [['label' => 'Home', 'url' => null]];
        }

        if (request()->routeIs('blog.index')) {
            $category = trim((string) request()->query('category', ''));
            $query = trim((string) request()->query('q', ''));

            if ($category !== '') {
                return [
                    $home,
                    ['label' => 'Blog', 'url' => route('blog.index')],
                    ['label' => $category, 'url' => null],
                ];
            }

            if ($query !== '') {
                return [
                    $home,
                    ['label' => 'Blog', 'url' => route('blog.index')],
                    ['label' => 'Search: ' . Str::limit($query, 36), 'url' => null],
                ];
            }

            return [$home, ['label' => 'Blog', 'url' => null]];
        }

        if (request()->routeIs('blog.show')) {
            $slug = (string) request()->route('slug');
            $post = Blog::query()
                ->where('is_published', true)
                ->where('slug', $slug)
                ->first();

            $trail = [$home, ['label' => 'Blog', 'url' => route('blog.index')]];

            if ($post?->category) {
                $trail[] = [
                    'label' => $post->category,
                    'url' => route('blog.index', ['category' => $post->category]),
                ];
            }

            $trail[] = [
                'label' => $post ? Str::limit($post->title, 48) : 'Article',
                'url' => null,
            ];

            return $trail;
        }

        if (request()->routeIs('deals.index')) {
            return [$home, ['label' => 'Deals', 'url' => null]];
        }

        if (request()->routeIs('landing.show')) {
            $campaign = request()->attributes->get('landing_campaign');
            $brandName = $campaign?->brand?->name;

            return [
                $home,
                ['label' => $brandName ? Str::limit($brandName, 48) : 'Store', 'url' => null],
            ];
        }

        $pageLabels = [
            'about' => 'About Us',
            'contact' => 'Contact',
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of Use',
            'cookie-policy' => 'Cookie Policy',
            'affiliate-disclosure' => 'Affiliate Disclosure',
        ];

        $path = trim(request()->path(), '/');
        if (isset($pageLabels[$path])) {
            return [$home, ['label' => $pageLabels[$path], 'url' => null]];
        }

        if ($path !== '') {
            return [$home, ['label' => Str::title(str_replace('-', ' ', $path)), 'url' => null]];
        }

        return [$home];
    }
}
