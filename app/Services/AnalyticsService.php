<?php

namespace App\Services;

use App\Models\BlockedIp;
use App\Models\Campaign;
use App\Models\PageView;
use App\Models\Click;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class AnalyticsService
{
    /**
     * Track a page view (skipped if IP is blocked)
     */
    public function trackPageView(Campaign $campaign, Request $request): ?PageView
    {
        $ip = app(ClientIpService::class)->getClientIp($request);
        if ($this->isAdminActiveIp($ip)) {
            return null;
        }
        $userId = $campaign->brand?->user_id;
        if (! PageView::isGoogleCrawler($request->userAgent())
            && $this->shouldAutoBlockForRateLimit($ip, $userId, $campaign, 'view')) {
            return null;
        }
        if (BlockedIp::isBlocked($ip, $userId)) {
            return null;
        }

        $sessionId = Session::getId();
        $userAgent = $request->userAgent();
        $isBot = PageView::isBot($userAgent);
        
        // Get device info
        $deviceType = PageView::getDeviceType($userAgent);
        $browser = PageView::getBrowser($userAgent);
        $os = PageView::getOS($userAgent);
        
        // Get location (simplified - in production, use a geolocation service)
        $country = $this->getCountryFromIP($ip);
        $city = null; // Would need a geolocation service for this
        
        return PageView::create([
            'campaign_id' => $campaign->id,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => $request->header('referer'),
            'session_id' => $sessionId,
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
            'country' => $country,
            'city' => $city,
            'is_bounce' => true, // Will be updated if user stays
            'is_bot' => $isBot,
        ]);
    }

    /**
     * Track a click (skipped if IP is blocked)
     */
    public function trackClick(Campaign $campaign, Request $request): ?Click
    {
        $ip = app(ClientIpService::class)->getClientIp($request);
        if ($this->isAdminActiveIp($ip)) {
            return null;
        }
        $userId = $campaign->brand?->user_id;
        if (! PageView::isGoogleCrawler($request->userAgent())
            && $this->shouldAutoBlockForRateLimit($ip, $userId, $campaign, 'click')) {
            return null;
        }
        if (BlockedIp::isBlocked($ip, $userId)) {
            return null;
        }

        $userAgent = $request->userAgent();
        $isBot = PageView::isBot($userAgent);
        
        // Get device info
        $deviceType = PageView::getDeviceType($userAgent);
        $browser = PageView::getBrowser($userAgent);
        $os = PageView::getOS($userAgent);
        
        // Get location
        $country = $this->getCountryFromIP($ip);
        $city = null;
        
        return Click::create([
            'campaign_id' => $campaign->id,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'referer' => $request->header('referer'),
            'sub_id' => $request->get('sub_id'),
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
            'country' => $country,
            'city' => $city,
            'is_bot' => $isBot,
        ]);
    }

    private function getCountryFromIP(string $ip): ?string
    {
        return app(GeoIpService::class)->getCountry($ip);
    }

    /**
     * If an IP generates >= 3 events within the same second, stop tracking and report immediately.
     */
    private function shouldAutoBlockForRateLimit(string $ip, ?int $userId, Campaign $campaign, string $eventType): bool
    {
        if ($ip === '' || $userId === null) {
            return false;
        }

        $secondKey = now()->format('YmdHis');
        $rateKey = "rate_limit:{$userId}:{$ip}:{$secondKey}";

        $count = (int) Cache::get($rateKey, 0) + 1;
        Cache::put($rateKey, $count, now()->addSeconds(2));

        if ($count < 3) {
            return false;
        }

        // Auto-block from stats for this user (do not block public pages by default)
        BlockedIp::firstOrCreate(
            ['ip' => $ip, 'user_id' => $userId],
            [
                'reason' => "Auto-block: suspected bot ({$eventType} >= 3/s) campaign_id={$campaign->id}",
                'block_public' => false,
            ]
        );

        // Avoid spamming notifications for the same IP
        $notifyKey = "rate_limit_notified:{$userId}:{$ip}";
        if (! Cache::has($notifyKey)) {
            if ($user = User::find($userId)) {
                Notification::make()
                    ->title('Phát hiện bot nghi ngờ (IP bị ngưng thống kê)')
                    ->body("IP {$ip} đã tạo {$count} {$eventType}(s) trong 1 giây trên chiến dịch \"{$campaign->title}\". Hệ thống đã tự động ngưng thống kê IP này.")
                    ->warning()
                    ->icon('heroicon-o-shield-exclamation')
                    ->sendToDatabase($user);
            }

            Cache::put($notifyKey, true, now()->addMinutes(10));
        }

        return true;
    }

    private function isAdminActiveIp(string $ip): bool
    {
        if (trim($ip) === '') {
            return false;
        }

        return (bool) Cache::get('admin_active_ip:' . $ip, false);
    }

    /**
     * Calculate CTR (Click Through Rate)
     */
    public function calculateCTR(Campaign $campaign, $startDate = null, $endDate = null): float
    {
        $viewsQuery = $campaign->pageViews()->where('is_bot', false)->forAdminStats();
        $clicksQuery = $campaign->clicks()->where('is_bot', false)->forAdminStats();
        
        if ($startDate) {
            $viewsQuery->whereDate('created_at', '>=', $startDate);
            $clicksQuery->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $viewsQuery->whereDate('created_at', '<=', $endDate);
            $clicksQuery->whereDate('created_at', '<=', $endDate);
        }
        
        $views = $viewsQuery->count();
        $clicks = $clicksQuery->count();
        
        if ($views === 0) {
            return 0;
        }
        
        return round(($clicks / $views) * 100, 2);
    }

    /**
     * Calculate unique visitors
     */
    public function getUniqueVisitors(Campaign $campaign, $startDate = null, $endDate = null): int
    {
        $query = $campaign->pageViews()
            ->where('is_bot', false)
            ->forAdminStats()
            ->selectRaw('COUNT(DISTINCT CONCAT(ip, "-", COALESCE(session_id, ""))) as unique_visitors');
        
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        return $query->value('unique_visitors') ?? 0;
    }

    /**
     * Calculate bounce rate
     */
    public function calculateBounceRate(Campaign $campaign, $startDate = null, $endDate = null): float
    {
        $query = $campaign->pageViews()->where('is_bot', false)->forAdminStats();
        
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        $totalViews = $query->count();
        $bounces = $query->where('is_bounce', true)->count();
        
        if ($totalViews === 0) {
            return 0;
        }
        
        return round(($bounces / $totalViews) * 100, 2);
    }

    /**
     * Calculate average time on page
     */
    public function getAverageTimeOnPage(Campaign $campaign, $startDate = null, $endDate = null): float
    {
        $query = $campaign->pageViews()
            ->where('is_bot', false)
            ->forAdminStats()
            ->whereNotNull('time_on_page');
        
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        return round($query->avg('time_on_page') ?? 0, 2);
    }
}

