<?php

namespace App\Models;

use App\Models\Concerns\ExcludesVietnamFromAdminStats;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    use ExcludesVietnamFromAdminStats;
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'ip',
        'user_agent',
        'referer',
        'session_id',
        'device_type',
        'browser',
        'os',
        'country',
        'city',
        'time_on_page',
        'is_bounce',
        'is_bot',
    ];

    protected $casts = [
        'is_bounce' => 'boolean',
        'is_bot' => 'boolean',
        'time_on_page' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get device type from user agent
     */
    public static function getDeviceType($userAgent): string
    {
        if (empty($userAgent)) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);
        
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
            return 'mobile';
        }
        
        if (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            return 'tablet';
        }
        
        return 'desktop';
    }

    /**
     * Get browser from user agent
     */
    public static function getBrowser($userAgent): string
    {
        if (empty($userAgent)) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'chrome') !== false && strpos($userAgent, 'edg') === false) {
            return 'Chrome';
        }
        if (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        }
        if (strpos($userAgent, 'safari') !== false && strpos($userAgent, 'chrome') === false) {
            return 'Safari';
        }
        if (strpos($userAgent, 'edg') !== false) {
            return 'Edge';
        }
        if (strpos($userAgent, 'opera') !== false || strpos($userAgent, 'opr') !== false) {
            return 'Opera';
        }
        
        return 'Other';
    }

    /**
     * Get OS from user agent
     */
    public static function getOS($userAgent): string
    {
        if (empty($userAgent)) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);
        
        if (strpos($userAgent, 'windows') !== false) {
            return 'Windows';
        }
        if (strpos($userAgent, 'mac') !== false) {
            return 'macOS';
        }
        if (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        }
        if (strpos($userAgent, 'android') !== false) {
            return 'Android';
        }
        if (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false) {
            return 'iOS';
        }
        
        return 'Other';
    }

    /** Googlebot / AdsBot — không chặn trang, không auto-block IP. */
    public static function isGoogleCrawler(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }

        $ua = strtolower($userAgent);

        return str_contains($ua, 'googlebot')
            || str_contains($ua, 'adsbot-google')
            || str_contains($ua, 'mediapartners-google')
            || str_contains($ua, 'apis-google')
            || str_contains($ua, 'feedfetcher-google')
            || str_contains($ua, 'google-inspectiontool')
            || str_contains($ua, 'storebot-google');
    }

    /**
     * Detect whether a request comes from a bot/crawler (for stats only).
     */
    public static function isBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false;
        }

        $ua = strtolower($userAgent);

        if (self::isGoogleCrawler($userAgent)) {
            return true;
        }

        // Common bot indicators
        $botKeywords = [
            'bot',
            'crawl',
            'spider',
            'slurp',
            'crawler',
            'preview',
            'python-requests',
            'curl',
            'wget',
            'httpclient',
            'headless',
            'phantomjs',
            'selenium',
            'facebookexternalhit',
            'monitor',
            'pingdom',
            'uptime',
            'validator',
            // major search engines
            'googlebot',
            'bingbot',
            'yandex',
            'duckduckbot',
            'baiduspider',
            'sogou',
            'exabot',
            'ahrefsbot',
            'semrush',
        ];

        foreach ($botKeywords as $keyword) {
            if (str_contains($ua, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
