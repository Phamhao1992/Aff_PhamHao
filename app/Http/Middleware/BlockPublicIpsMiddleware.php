<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Services\ClientIpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockPublicIpsMiddleware
{
    /**
     * Chặn IP có block_public=true truy cập các trang public.
     * Cho phép truy cập /admin (trang quản trị).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin*')) {
            return $next($request);
        }

        if (\App\Models\PageView::isGoogleCrawler($request->userAgent())) {
            return $next($request);
        }

        $ip = app(ClientIpService::class)->getClientIp($request);
        if (BlockedIp::isBlockedFromPublic($ip)) {
            abort(403, 'Quyền truy cập bị từ chối.');
        }

        return $next($request);
    }
}
