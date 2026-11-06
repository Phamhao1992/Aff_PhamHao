<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Campaign;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate sitemap.xml for SEO.
     */
    public function index(): Response
    {
        $base = rtrim(config('app.url'), '/');

        $urls = [
            ['loc' => $base . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base . '/blog', 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $base . '/deals', 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => $base . '/about', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => $base . '/contact', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => $base . '/privacy', 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['loc' => $base . '/cookie-policy', 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['loc' => $base . '/terms', 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['loc' => $base . '/affiliate-disclosure', 'changefreq' => 'monthly', 'priority' => '0.4'],
        ];

        $posts = Blog::query()
            ->where('is_published', true)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        foreach ($posts as $post) {
            $urls[] = [
                'loc' => $base . '/blog/' . $post->slug,
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $post->updated_at?->toAtomString(),
            ];
        }

        $campaigns = Campaign::query()
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        foreach ($campaigns as $campaign) {
            $slug = ltrim((string) $campaign->slug, '/');
            if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
                continue;
            }
            $urls[] = [
                'loc' => $base . '/store/' . $slug,
                'changefreq' => 'daily',
                'priority' => '0.8',
                'lastmod' => $campaign->updated_at?->toAtomString(),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc']) . '</loc>' . "\n";
            if (! empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
            }
            $xml .= '    <changefreq>' . ($u['changefreq'] ?? 'weekly') . '</changefreq>' . "\n";
            $xml .= '    <priority>' . ($u['priority'] ?? '0.5') . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
