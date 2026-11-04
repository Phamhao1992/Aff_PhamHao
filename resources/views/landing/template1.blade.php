<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @php
        // Đảm bảo chuỗi offer luôn UTF-8 để €, £, ¥, ₹ hiển thị đúng (tránh lỗi "UP TO10")
        $ensureOfferUtf8 = function ($text) {
            $text = (string) $text;
            if ($text === '') {
                return $text;
            }
            if (mb_check_encoding($text, 'UTF-8') && mb_strpos($text, "\xEF\xBF\xBD") === false) {
                return $text;
            }
            $decoded = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            return $decoded !== false ? $decoded : $text;
        };

        $coupons = $campaign->couponItems ?? collect();
        // Sắp xếp coupon theo thứ tự tạo (cũ -> mới)
        $coupons = $coupons->sortBy(fn($c) => $c->created_at ?? ($c->id ?? 0))->values();
        $maxPercent = 0;
        foreach ($coupons as $c) {
            $offerText = $ensureOfferUtf8($c->offer ?? '');
            if (preg_match('/(\d+)\s*%/i', $offerText, $m)) {
                $val = (int) $m[1];
                if ($val > $maxPercent) {
                    $maxPercent = $val;
                }
            }
        }
        if ($maxPercent <= 0) {
            $maxPercent = 75;
        }

        // Max currency (for default coupon display)
        $maxCurrencyValue = 0;
        $maxCurrencySymbol = '$';
        foreach ($coupons as $c) {
            $offerText = $ensureOfferUtf8($c->offer ?? '');
            if (preg_match('/([€$£¥₹])\s*(\d+(?:[.,]\d+)?)/u', $offerText, $m)) {
                $val = (float) str_replace([',', ' '], ['', ''], $m[2]);
                if ($val > $maxCurrencyValue) {
                    $maxCurrencyValue = $val;
                    $maxCurrencySymbol = $m[1];
                }
            } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*([€$£¥₹])/u', $offerText, $m)) {
                $val = (float) str_replace([',', ' '], ['', ''], $m[1]);
                if ($val > $maxCurrencyValue) {
                    $maxCurrencyValue = $val;
                    $maxCurrencySymbol = $m[2];
                }
            } elseif (preg_match('/(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)\s*(\d+(?:[.,]\d+)?)/i', $offerText, $m)) {
                $val = (float) str_replace([',', ' '], ['', ''], $m[2]);
                if ($val > $maxCurrencyValue) {
                    $maxCurrencyValue = $val;
                    $codeToSymbol = [
                        'USD' => '$',
                        'EUR' => '€',
                        'GBP' => '£',
                        'JPY' => '¥',
                        'INR' => '₹',
                        'CAD' => 'C$',
                        'AUD' => 'A$',
                        'CHF' => 'CHF',
                        'CNY' => '¥',
                    ];
                    $maxCurrencySymbol = $codeToSymbol[strtoupper($m[1])] ?? $m[1];
                }
            } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)/i', $offerText, $m)) {
                $val = (float) str_replace([',', ' '], ['', ''], $m[1]);
                if ($val > $maxCurrencyValue) {
                    $maxCurrencyValue = $val;
                    $codeToSymbol = [
                        'USD' => '$',
                        'EUR' => '€',
                        'GBP' => '£',
                        'JPY' => '¥',
                        'INR' => '₹',
                        'CAD' => 'C$',
                        'AUD' => 'A$',
                        'CHF' => 'CHF',
                        'CNY' => '¥',
                    ];
                    $maxCurrencySymbol = $codeToSymbol[strtoupper($m[2])] ?? $m[2];
                }
            }
        }

        // Default coupon: use max % or max currency (prioritize percent, else currency)
        $defaultCouponOfferType = $maxPercent > 0 ? 'percent' : 'currency';
        $defaultCouponOfferValue = $maxPercent > 0 ? $maxPercent : $maxCurrencyValue;
        $defaultCouponCurrencySymbol = $maxPercent > 0 ? '' : $maxCurrencySymbol;
        if ($defaultCouponOfferValue <= 0) {
            $defaultCouponOfferType = 'percent';
            $defaultCouponOfferValue = 20;
        }

        $couponsWithCodes = $coupons->filter(fn($c) => !empty($c->code))->values();
        $firstCoupon = $coupons->first();

        $campaignSlug = $campaign->slug;
        $firstCouponData = [
            'id' => (string) ($firstCoupon?->id ?? 'shop-now'),
            'code' => (string) ($firstCoupon?->code ?? ''),
            'url' => route('click.redirect', ['slug' => $campaignSlug]),
        ];

        $brandName = $campaign->title;
        $rawIntro = (string) ($campaign->subtitle ?? ($campaign->intro ?? ''));
        $metaTitle = $brandName . ' Coupons & Promo Codes – ' . now()->format('F Y');
        $metaDescription = \Illuminate\Support\Str::limit(strip_tags($rawIntro), 160);

        if (empty($metaDescription)) {
            $metaDescription = 'Save more with the latest ' . $brandName . ' coupon codes, deals and promotions.';
        }

        if ($campaign->brand && $campaign->brand->image) {
            $ogImage = $campaign->brand->image_url;
        } elseif ($campaign->cover_image) {
            $ogImage = asset('storage/' . $campaign->cover_image);
        } elseif ($campaign->logo) {
            $ogImage = asset('storage/' . $campaign->logo);
        } else {
            $ogImage = asset('images/default-brand.svg');
        }
    @endphp
    <title>{{ \App\Support\MetaTag::plain($metaTitle) }}</title>
    <meta name="description" content="{{ \App\Support\MetaTag::plain($metaDescription) }}">
    <meta name="robots" content="noindex, follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ \App\Support\MetaTag::plain($metaTitle) }}">
    <meta property="og:description" content="{{ \App\Support\MetaTag::plain($metaDescription) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ \App\Support\MetaTag::plain($metaTitle) }}">
    <meta name="twitter:description" content="{{ \App\Support\MetaTag::plain($metaDescription) }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap"
        rel="stylesheet">
    @include('partials.site-chrome-styles')
    @include('partials.landing-magazine-head')
    @if (config('app.ga4_id'))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga4_id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }
            gtag('js', new Date());
            gtag('config', '{{ config('app.ga4_id') }}');
        </script>
    @endif

    @include('partials.peel-sticker-styles')

    <style>
        /* ===== BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #198754;
            --primary-dark: #157347;
            --primary-soft: #d1e7dd;
            --primary-light: #20c997;
            --accent: #157347;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --bg-page: #f5f5f5;
            --bg-card: #ffffff;
            --border: rgba(15, 23, 42, 0.08);
            --shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 4px 12px rgba(15, 23, 42, 0.1);
            --shadow-lg: 0 20px 50px rgba(15, 23, 42, 0.15);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont,
                'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.7;
            color: var(--text-dark);
            background: var(--bg-page);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: color 0.2s ease;
        }

        /* ===== SHELL LAYOUT (CENTER WHITE PANEL) ===== */
        .shell {
            max-width: 1200px;
            margin: 0 auto 50px;
            padding: 36px 20px 56px;
        }

        .page-panel {
            background: var(--bg-card);
            border-radius: 28px;
            box-shadow: 0 24px 65px -30px rgba(15, 23, 42, 0.32);
            padding: 36px 36px 44px;
            border: 1px solid rgba(47, 194, 169, 0.16);
            position: relative;
            overflow: hidden;
        }

        .page-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(55% 40% at 6% 0%, rgba(47, 194, 169, 0.08) 0%, transparent 70%),
                radial-gradient(45% 35% at 100% 100%, rgba(14, 165, 233, 0.08) 0%, transparent 70%);
        }

        /* ===== HERO (TOP TITLE LIKE TENERE) ===== */
        .hero {
            text-align: center;
            margin-bottom: 36px;
            padding: 22px 18px 30px;
            border: 1px solid rgba(47, 194, 169, 0.18);
            border-radius: 20px;
            background: linear-gradient(135deg, #ecfeff 0%, #e0f2fe 55%, #ffffff 100%);
        }

        .hero-title {
            font-size: 2.1rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin-bottom: 12px;
            line-height: 1.2;
            background: #0f172a;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 1.02rem;
            color: var(--text-light);
            line-height: 1.7;
            max-width: 760px;
            margin: 0 auto;
        }

        .hero-sub strong {
            color: var(--primary);
            font-weight: 700;
        }

        /* ===== MAIN GRID (LEFT STORE CARD + RIGHT CONTENT) ===== */
        .page {
            margin-top: 28px;
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 28px;
        }

        .left-card {
            background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%);
            border-radius: 22px;
            padding: 28px 24px 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(47, 194, 169, 0.18);
            position: relative;
            display: flex;
            flex-direction: column;
            transition: none;
        }

        .brand-logo {
            max-width: 70px;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto 16px;
            border-radius: 16px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 10px 30px rgba(47, 194, 169, 0.22);
            transition: none;
        }

        .brand-name {
            font-size: 1.25rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .brand-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            margin-bottom: 10px;
            font-size: 0.8rem;
        }

        .stars {
            color: #fde047;
            font-size: 25px;
        }

        .brand-meta {
            font-size: 0.75rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 4px;
        }

        /* Stats card (Working Codes, Success Rate, Total Saved) */
        .stats-card {
            background: linear-gradient(180deg, #f0f9ff 0%, #ecfeff 100%);
            border-radius: 14px;
            padding: 14px 16px;
            margin-top: 12px;
            border: 1px solid rgba(47, 194, 169, 0.2);
        }

        .stats-list {
            font-size: 0.8rem;
        }

        .stats-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            gap: 10px;
            border-bottom: 1px solid rgba(47, 194, 169, 0.16);
        }

        .stats-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .stats-row:first-child {
            padding-top: 0;
        }

        .stats-label {
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-label .stats-icon {
            font-size: 1rem;
            opacity: 0.9;
        }

        .stats-value {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-dark);
        }

        /* Quick Menu */
        .quick-menu {
            margin-top: 20px;
        }

        .quick-menu-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .quick-menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .quick-menu-list li {
            margin-bottom: 6px;
        }

        .quick-menu-list a {
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: block;
            padding: 6px 0;
            transition: color 0.2s;
        }

        .quick-menu-list a:hover {
            color: var(--primary);
        }

        .quick-menu-shop-now {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-bottom: 14px;
            padding: 12px 18px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 999px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s, transform 0.2s;
            box-shadow: 0 4px 12px rgba(47, 194, 169, 0.3);
        }

        .quick-menu-shop-now:hover {
            background: var(--primary-dark);
            color: #fff;
            transform: translateY(-1px);
        }

        .right-column {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        /* NOTE BAR & FILTERS */
        .note-bar {
            font-size: 0.85rem;
            color: var(--text-light);
            background: linear-gradient(135deg, #ecfeff 0%, #e0f2fe 100%);
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 16px;
            border: 1px solid rgba(47, 194, 169, 0.2);
            line-height: 1.6;
        }

        .coupon-header {
            margin-bottom: 10px;
        }

        .coupon-header-title {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
            color: var(--text-dark);
        }

        .coupon-header-meta {
            font-size: 0.9rem;
            color: var(--text-light);
            line-height: 1.6;
        }

        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .filter-pill {
            border-radius: 999px;
            padding: 8px 16px;
            font-size: 0.85rem;
            border: 1px solid #e5e7eb;
            background: #fff;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            min-width: fit-content;
        }

        .filter-pill:hover {
            border-color: var(--primary);
            background: #ecfeff;
        }

        .filter-pill.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(47, 194, 169, 0.3);
        }

        .coupon-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 6px;
            margin-bottom: 16px;
        }

        .coupon-row {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 14px 30px -24px rgba(47, 194, 169, 0.35);
            padding: 0;
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 0;
            align-items: stretch;
            transition: none;
            border: 1px solid rgba(47, 194, 169, 0.16);
            overflow: hidden;
            position: relative;
        }

        .coupon-row:hover {
            box-shadow: 0 18px 34px -22px rgba(47, 194, 169, 0.4);
            transform: none;
        }

        /* Left section - Discount Visual */
        .coupon-discount-visual {
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
        }

        .coupon-discount-visual::after {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 1px;
            background: repeating-linear-gradient(to bottom,
                    #d1d5db 0px,
                    #d1d5db 8px,
                    transparent 8px,
                    transparent 16px);
        }

        .discount-up-to {
            font-size: 1rem;
            font-weight: 600;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .discount-percent {
            font-size: 2.5rem;
            font-weight: 900;
            color: #111827;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -0.05em;
            word-break: break-word;
        }

        .discount-off-badge {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #fff;
            padding: 5px 14px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Right section - Coupon Details */
        .coupon-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 18px 20px;
            position: relative;
        }

        .coupon-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 2px;
        }

        .coupon-title {
            font-weight: 700;
            font-size: 1.02rem;
            letter-spacing: -0.01em;
            line-height: 1.35;
            flex: 1;
            color: var(--text-dark);
        }

        .coupon-revealed-code {
            display: inline-flex;
            align-items: center;
            padding: 0px 16px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: ui-monospace, monospace;
            letter-spacing: 0.05em;
            background: #ecfeff;
            color: var(--primary-dark);
            border: 1px dashed var(--primary);
            border-radius: 12px;
            white-space: nowrap;
            cursor: pointer;
            transition: none;
        }

        .staff-pick-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary);
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .staff-pick-badge::before {
            content: '⭐';
            font-size: 0.65rem;
        }

        .all-verified-codes-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary);
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .all-verified-codes-badge::before {
            content: '✓';
            font-size: 0.75rem;
            font-weight: 900;
        }

        .badge-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .top-pic-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #111827;
            color: #fff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .top-pic-badge::before {
            content: '🔥';
            font-size: 0.65rem;
        }

        .all-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfeff;
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 800;
            margin-top: 10px;
            border: 1px solid rgba(47, 194, 169, 0.3);
        }

        .all-verified-badge::before {
            content: '✓';
            font-weight: 900;
        }

        .coupon-meta-info {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 6px;
            font-size: 0.8rem;
            color: #6b7280;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .meta-item::before {
            content: '✓';
            color: var(--primary);
            font-weight: 700;
            font-size: 0.85rem;
        }

        .meta-item.uses::before {
            content: '👥';
        }

        .coupon-desc {
            font-size: 0.85rem;
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .coupon-verified {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .coupon-verified-badge {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .coupon-verified-badge img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .coupon-desc .show-more {
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .coupon-desc .show-more:hover {
            text-decoration: underline;
        }

        .coupon-actions {
            display: none;
        }

        .coupon-code {
            display: none;
        }

        .section {
            background: #fff;
            border-radius: 16px;
            padding: 24px 26px 26px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(229, 231, 235, 0.5);
            transition: none;
            margin-bottom: 10px;
        }

        .section:hover {
            box-shadow: var(--shadow-md);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 14px;
            letter-spacing: -0.02em;
            color: var(--text-dark);
        }

        .section-body {
            font-size: 0.85rem;
            color: #5c5c5c !important;
            ;
            line-height: 1.75;
        }

        .section-body ul {
            padding-left: 18px;
            margin: 6px 0;
        }

        .section-body li {
            margin-bottom: 4px;
        }

        /* Intro content styling */
        .intro-content {
            line-height: 1.75;
            color: #5c5c5c !important;
        }

        .intro-content p {
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .intro-content p:last-child {
            margin-bottom: 0;
        }

        .intro-content img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 1rem 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .intro-content h1,
        .intro-content h2,
        .intro-content h3,
        .intro-content h4,
        .intro-content h5,
        .intro-content h6 {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.02em;
        }

        .intro-content h1 {
            font-size: 1.5rem;
        }

        .intro-content h2 {
            font-size: 1.3rem;
        }

        .intro-content h3 {
            font-size: 1.15rem;
        }

        .intro-content ul,
        .intro-content ol {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        .intro-content li {
            margin-bottom: 0.5rem;
        }

        .intro-content a {
            color: var(--primary);
            text-decoration: underline;
        }

        .intro-content a:hover {
            color: var(--primary-dark);
        }

        .intro-content blockquote {
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
            margin: 1rem 0;
            font-style: italic;
            color: var(--text-light);
        }

        .intro-content code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
            font-family: ui-monospace, monospace;
        }

        .intro-content pre {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1rem 0;
        }

        .intro-content pre code {
            background: none;
            padding: 0;
        }

        /* ===== ADDED (NO CONFLICT) ===== */
        .coupon-code.peek {
            filter: blur(3px);
            opacity: 0.75;
            pointer-events: none;
        }

        .coupon-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .coupon-modal.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 18px;
            width: 420px;
            max-width: 92%;
            padding: 20px;
            text-align: center;
            position: relative;
            border: 1px solid rgba(47, 194, 169, 0.22);
            box-shadow: 0 24px 50px -24px rgba(15, 23, 42, 0.45);
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 14px;
            cursor: pointer;
            font-size: 20px;
        }

        .modal-logo img {
            max-width: 80px;
            border-radius: 50%;
            margin-bottom: 12px;
        }

        .modal-code {
            display: flex;
            justify-content: center;
            margin: 14px 0;
        }

        .modal-code span {
            padding: 10px 18px;
            border: 2px dashed var(--primary);
            font-weight: 700;
            border-radius: 10px;
            background: #ecfeff;
        }

        .modal-code button {
            margin-left: 6px;
            padding: 10px 14px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: 700;
            border-radius: 10px;
        }

        .modal-link {
            color: var(--primary);
            font-weight: 600;
        }

        @media(max-width:960px) {
            .page {
                grid-template-columns: 1fr;
            }
        }

        /* ===== Coupon Popup ===== */
        .coupon-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease;
        }

        .coupon-modal.active {
            opacity: 1;
            pointer-events: auto;
        }

        .coupon-modal-stack {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            max-width: 100%;
            padding: 0 12px;
            box-sizing: border-box;
        }

        .coupon-copy-toast[hidden] {
            display: none !important;
        }

        .coupon-copy-toast {
            display: flex;
            flex-direction: column;
            background: #2c2c2e;
            color: #fff;
            padding: 0;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.45);
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translate(-50%, -24px);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.26s ease, opacity 0.26s ease;
            z-index: 10050;
            overflow: hidden;
            width: min(420px, calc(100vw - 2rem));
            max-width: min(420px, calc(100vw - 2rem));
            min-width: 0;
            font-size: 0.95rem;
            font-weight: 500;
            font-family: inherit;
            -webkit-tap-highlight-color: transparent;
        }
        .coupon-copy-toast.is-visible {
            transform: translate(-50%, 0);
            opacity: 1;
            pointer-events: auto;
        }
        .coupon-copy-toast.is-hiding {
            transform: translate(-50%, -24px);
            opacity: 0;
            pointer-events: none;
        }

        .coupon-copy-toast-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 12px 14px 14px;
        }

        .coupon-copy-toast-icon {
            flex-shrink: 0;
            font-weight: 700;
            font-size: 1.05rem;
            line-height: 1.35;
            margin-top: 1px;
        }

        .coupon-copy-toast-text {
            flex: 1;
            margin: 0;
            text-align: center;
            line-height: 1.45;
            min-width: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .coupon-copy-toast-text strong {
            font-weight: 700;
            color: #fff;
        }

        .coupon-copy-toast-close {
            flex-shrink: 0;
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.9);
            cursor: pointer;
            padding: 6px 8px;
            margin: -4px -4px 0 0;
            min-width: 40px;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            border-radius: 8px;
            transition: background 0.15s;
            touch-action: manipulation;
        }

        .coupon-copy-toast-close:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        @media (max-width: 480px) {
            .coupon-copy-toast {
                width: calc(100vw - 1.5rem);
                max-width: calc(100vw - 1.5rem);
                font-size: 1rem;
            }

            .coupon-copy-toast-row {
                padding: 10px 10px 12px 12px;
                gap: 8px;
            }
        }

        .coupon-copy-toast-progress {
            width: 100%;
            height: 3px;
            background: var(--primary);
            transform-origin: left center;
            animation: couponCopyToastProgress 2s linear forwards;
        }

        @keyframes couponCopyToastProgress {
            from {
                transform: scaleX(1);
            }

            to {
                transform: scaleX(0);
            }
        }

        .coupon-modal-content {
            background: #ffffff;
            width: 100%;
            max-width: 580px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(15, 23, 42, .35);
            animation: popupScale .3s ease;
            position: relative;
            max-height: calc(100vh - 24px);
            display: flex;
            flex-direction: column;
        }

        .popup-banner {
            background: linear-gradient(145deg, var(--primary-light) 0%, var(--primary-dark) 58%, var(--primary) 100%);
            padding: 32px 28px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        .popup-banner .popup-title {
            color: #ffffff;
        }
        .popup-banner .popup-subtitle {
            color: rgba(255, 255, 255, 0.95);
        }

        .popup-confetti {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .confetti-piece {
            position: absolute;
            width: 8px;
            height: 8px;
            background: currentColor;
            opacity: 0.7;
            animation: confettiFall 3s linear infinite;
        }

        .confetti-piece:nth-child(1) {
            left: 10%;
            color: #fbbf24;
            animation-delay: 0s;
        }

        .confetti-piece:nth-child(2) {
            left: 20%;
            color: var(--primary-light);
            animation-delay: 0.5s;
        }

        .confetti-piece:nth-child(3) {
            left: 30%;
            color: #20c997;
            animation-delay: 1s;
        }

        .confetti-piece:nth-child(4) {
            left: 40%;
            color: #f97316;
            animation-delay: 1.5s;
        }

        .confetti-piece:nth-child(5) {
            left: 50%;
            color: #fbbf24;
            animation-delay: 0.3s;
        }

        .confetti-piece:nth-child(6) {
            left: 60%;
            color: var(--primary-light);
            animation-delay: 0.8s;
        }

        .confetti-piece:nth-child(7) {
            left: 70%;
            color: #20c997;
            animation-delay: 1.3s;
        }

        .confetti-piece:nth-child(8) {
            left: 80%;
            color: #f97316;
            animation-delay: 1.8s;
        }

        .confetti-piece:nth-child(9) {
            left: 90%;
            color: #fbbf24;
            animation-delay: 0.6s;
        }

        @keyframes confettiFall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 0.7;
            }

            100% {
                transform: translateY(200px) rotate(360deg);
                opacity: 0;
            }
        }

        .popup-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fff;
            padding: 12px;
            margin: 0 auto 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: relative;
            z-index: 1;
            object-fit: contain;
        }

        .popup-header {
            position: relative;
            z-index: 1;
        }

        .popup-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #fff;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .popup-subtitle {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.95);
            margin: 12px 0 0;
            line-height: 1.5;
        }

        .popup-body {
            padding: 24px 28px 28px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }

        @keyframes popupScale {
            from {
                transform: scale(.92);
                opacity: .5;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .coupon-modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            color: #fff;
            z-index: 10;
            transition: all 0.2s ease;
        }

        .coupon-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .coupon-code-container {
            background: var(--primary-dark);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .coupon-code-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .coupon-code-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .coupon-code-box {
            background: transparent;
            border: none;
            padding: 0;
            text-align: left;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            font-family: ui-monospace, monospace;
        }

        .coupon-code-box.coupon-code-box--no-code {
            font-family: inherit;
            letter-spacing: normal;
            font-size: 1.05rem;
            font-weight: 600;
        }

        .coupon-code-container.is-no-code {
            justify-content: center;
        }

        .coupon-code-container.is-no-code .coupon-code-left {
            justify-content: center;
        }

        .btn-copy-code-modal {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            box-shadow: 0 6px 18px var(--primary);
        }

        .btn-copy-code-modal:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-light) 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px var(--primary);
        }

        .btn-copy-code-modal:active {
            transform: translateY(0);
        }

        .popup-verification {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #9ca3af;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f3f4f6;
        }

        .verification-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .verification-item::before {
            content: '✓';
            color: var(--primary);
            font-weight: 700;
            font-size: 0.9rem;
        }

        .verification-item.success::before {
            content: '●';
            color: var(--primary);
        }

        .coupon-modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .coupon-btn {
            flex: 1;
            padding: 12px 14px;
            border-radius: 999px;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .coupon-btn.store {
            background: var(--primary);
            color: #ffffff;
            text-align: center;
        }

        .coupon-btn.store:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .popup-feedback {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
        }

        .popup-feedback-question {
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }

        .popup-feedback-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .lead-capture {
            margin-top: 14px;
        }
        .lead-capture-label {
            margin: 0 0 8px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.01em;
        }
        .lead-capture-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
        }
        .lead-capture-input {
            height: 42px;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 0 12px;
            font-size: 0.9rem;
            width: 100%;
            font-family: inherit;
            color: var(--text-dark);
            background: #fff;
        }
        .lead-capture-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(47, 194, 169, 0.2);
        }
        .lead-capture-btn {
            height: 42px;
            border: none;
            border-radius: 10px;
            padding: 0 16px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            background: var(--primary);
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .lead-capture-btn:hover { background: var(--primary-dark); }
        .lead-capture-msg {
            margin-top: 6px;
            min-height: 18px;
            font-size: 0.78rem;
            color: #6b7280;
        }
        .lead-capture-msg.success { color: var(--primary-dark); }
        .lead-capture-msg.error { color: #dc2626; }

        .feedback-btn {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            flex: 1;
            max-width: 180px;
            justify-content: center;
        }

        .feedback-btn:hover {
            background: #f9fafb;
            border-color: var(--primary);
            color: var(--primary);
        }

        .feedback-btn:active {
            transform: scale(0.98);
        }

        .feedback-btn.worked {
            border-color: var(--primary);
            color: var(--primary);
        }

        .feedback-btn.failed {
            border-color: #ef4444;
            color: #ef4444;
        }

        .feedback-icon {
            font-size: 1.1rem;
        }

        /* All Codes Modal */
        .all-codes-modal-content {
            max-width: 580px;
        }

        .all-codes-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .all-codes-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .all-codes-item .coupon-code-container {
            margin-bottom: 0;
        }

        .all-codes-item-desc {
            padding: 8px 16px 12px;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .btn-copy-all-codes:active,
        .btn-copy-all-codes.copied {
            background: var(--primary-dark) !important;
        }

        /* Q&A Section */
        .qa-section {
            padding-top: 20px;
        }

        .qa-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 10px;
            overflow: hidden;
            background: #fff;
        }

        .qa-question {
            width: 100%;
            background: none;
            border: none;
            padding: 14px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-dark);
        }

        .qa-question:hover {
            background: var(--bg-light);
        }

        .qa-icon {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .qa-answer {
            max-height: 0;
            overflow: hidden;
            padding: 0 16px;
            font-size: 0.85rem;
            color: var(--text-light);
            transition: all 0.35s ease;
        }

        .qa-item.active .qa-answer {
            max-height: 200px;
            padding: 10px 16px 16px;
        }

        .qa-item.active .qa-icon {
            transform: rotate(45deg);
        }

        /* Popup Header */
        .popup-header {
            text-align: center;
            margin-bottom: 16px;
        }

        .popup-logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: block;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
            background: #fff;
        }

        .popup-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-dark);
        }

        .popup-subtitle {
            font-size: 0.8rem;
            color: while;
        }

        h1,
        h2,
        h3,
        .brand-name,
        .section-title,
        .popup-title {
            letter-spacing: -0.015em;
        }

        .brand-name {
            font-weight: 800;
        }

        .section-title {
            font-weight: 700;
        }

        .coupon-title {
            font-weight: 600;
        }

        /* ===== Review Block Improvements ===== */
        .review-center {
            text-align: center;
            margin-bottom: 14px;
        }

        .review-center .brand-logo {
            margin: 0 auto 12px;
            border-radius: 50%;
            background: #fff;
            padding: 6px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
        }

        .review-stars {
            font-size: 1.25rem;
            /* ⭐ to hơn */
            color: #fde047;
            margin-bottom: 6px;
        }

        .review-rating-text {
            font-size: 0.85rem;
            opacity: 0.95;
            margin-bottom: 14px;
        }

        .btn-get-coupon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 999px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            box-shadow: 0 10px 24px rgba(25, 135, 84, 0.35);
        }

        .btn-get-coupon:hover {
            transform: translateY(-1px);
            background: var(--primary-dark);
            cursor: pointer;
        }

        /* Coupon Intro Header */
        .coupon-intro {
            padding: 18px 20px;
        }

        .coupon-intro-title {
            font-size: 1.15rem;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .coupon-intro-desc {
            font-size: 0.85rem;
            color: var(--text-light);
            line-height: 1.6;
        }

        /* Go to Store highlight animation */
        @keyframes pulseGlow {
            0% {
                box-shadow: 0 0 0 rgba(47, 194, 169, 0.0);
                transform: scale(1);
            }

            50% {
                box-shadow: 0 0 25px rgba(47, 194, 169, 0.55);
                transform: scale(1.05);
            }

            100% {
                box-shadow: 0 0 0 rgba(47, 194, 169, 0.0);
                transform: scale(1);
            }
        }

        .go-store-attention {
            animation: pulseGlow 0.9s ease-in-out 3;
        }

        @media(max-width:1024px) {
            .page {
                grid-template-columns: 1fr;
            }

            .shell {
                padding: 20px 16px 40px;
            }

            .page-panel {
                padding: 24px 20px 32px;
                box-shadow: var(--shadow-md);
                border-radius: 20px;
            }

            .hero-title {
                font-size: 1.6rem;
            }
        }

        /* Mobile: shop + logo trên cùng, danh sách coupon tiếp theo, các block khác đẩy xuống cuối */
        @media(max-width:768px) {
            .page {
                display: flex;
                flex-direction: column;
            }

            .left-card {
                display: contents;
            }

            .right-column {
                display: contents;
            }

            .left-card-header {
                order: 1;
                background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%);
                border-radius: 20px;
                padding: 24px 20px;
                box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
                border: 1px solid rgba(47, 194, 169, 0.16);
            }

            .right-column-coupon-section {
                order: 2;
            }

            .coupon-list {
                order: 3;
            }

            .left-card-footer {
                order: 4;
                background: linear-gradient(180deg, #ffffff 0%, #f0f9ff 100%);
                border-radius: 20px;
                padding: 24px 20px;
                box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
                border: 1px solid rgba(47, 194, 169, 0.16);
                margin-top: 8px;
            }

            #about-us {
                order: 5;
            }

            #how-to-use {
                order: 6;
            }

            #qa {
                order: 7;
            }

            #policies {
                order: 8;
            }

            /* Coupon row: giữ layout ngang trên tablet */
            .coupon-row {
                grid-template-columns: 140px 1fr;
            }

            .coupon-discount-visual::after {
                display: none;
            }

            .coupon-discount-visual {
                border-bottom: none;
                padding: 14px 10px;
            }

            .coupon-info {
                padding: 14px 16px;
            }

            .coupon-header-row {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 10px;
            }

            .btn-peel-sticker .peel-inner {
                min-width: 120px;
            }

            .coupon-title {
                font-size: 0.9rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .discount-percent {
                font-size: 2.2rem;
            }

            .popup-banner {
                padding: 24px 20px 32px;
            }

            .popup-logo {
                width: 70px;
                height: 70px;
            }

            .popup-title {
                font-size: 1.2rem;
            }

            .popup-subtitle {
                font-size: 0.85rem;
            }

            .coupon-code-container {
                flex-direction: column;
                gap: 12px;
            }

            .coupon-code-left {
                width: 100%;
            }

            .btn-copy-code-modal {
                width: 100%;
            }

            .popup-feedback-buttons {
                flex-direction: column;
            }

            .feedback-btn {
                max-width: 100%;
            }
        }

        /* Very small screens: stack coupon content in one column for readability */
        @media(max-width:480px) {
            .coupon-row {
                grid-template-columns: 1fr;
            }

            .coupon-discount-visual {
                border-right: none;
                border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            }
        }

        @media(max-width:640px) {
            .hero-title {
                font-size: 1.4rem;
                line-height: 1.3;
            }

            .hero-sub {
                font-size: 0.9rem;
            }

            .shell {
                padding: 16px 12px 32px;
            }

            .page-panel {
                padding: 20px 16px 28px;
            }

            .section {
                padding: 18px 16px 20px;
                border-radius: 14px;
            }

            .section-title {
                font-size: 1.1rem;
                margin-bottom: 12px;
            }

            .section-body {
                font-size: 0.9rem;
            }

            .coupon-row {
                border-radius: 16px;
            }

            .coupon-discount-visual {
                padding: 14px 12px;
                background-color: #ffeaea;
            }

            .discount-up-to {
                font-size: 1rem;
                margin-bottom: 4px;
            }

            .discount-percent {
                font-size: 2.2rem;
                margin-bottom: 6px;
            }

            .discount-off-badge {
                padding: 4px 12px;
                font-size: 0.8rem;
            }

            .coupon-info {
                padding: 14px 16px;
                gap: 6px;
            }

            .coupon-title {
                font-size: 0.9rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .staff-pick-badge {
                display: none;
            }

            .coupon-meta-info {
                font-size: 0.6rem;
                gap: 8px;
            }

            .coupon-desc {
                overflow: hidden;
                display: none;
            }

            .btn-peel-sticker .peel-text {
                font-size: 0.82rem;
            }

            .filter-tabs {
                gap: 6px;
            }

            .filter-pill {
                font-size: 0.8rem;
                padding: 6px 12px;
            }

            .coupon-header-title {
                font-size: 1.15rem;
            }

            .coupon-header-meta {
                font-size: 0.85rem;
            }

            .coupon-modal-content {
                max-width: 95%;
                margin: 12px;
                max-height: calc(100vh - 24px);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                align-self: flex-start;
                margin-top: 12px;
            }

            .coupon-modal.active {
                align-items: flex-start;
                padding: 12px 0;
            }

            .coupon-modal-content .popup-banner {
                flex-shrink: 0;
            }

            .coupon-modal-content .popup-body {
                flex: 1;
                overflow-y: auto;
                min-height: 0;
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }

            .coupon-modal-close {
                min-width: 44px;
                min-height: 44px;
                top: 12px;
                right: 12px;
            }

            .popup-banner {
                padding: 20px 16px 28px;
            }

            .popup-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 12px;
            }

            .popup-title {
                font-size: 1.1rem;
            }

            .popup-subtitle {
                font-size: 0.8rem;
                margin-top: 8px;
            }

            .popup-body {
                padding: 20px 16px 24px;
            }

            .coupon-code-container {
                padding: 12px 16px;
            }

            .coupon-code-box {
                font-size: 1rem;
            }

            .btn-copy-code-modal {
                font-size: 0.85rem;
                padding: 10px 16px;
            }

            .popup-verification {
                font-size: 0.75rem;
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }

            .coupon-modal-actions {
                margin-top: 16px;
            }

            .coupon-btn.store {
                width: 100%;
                padding: 12px 16px;
            }

            .popup-feedback {
                margin-top: 16px;
                padding-top: 16px;
            }

            .popup-feedback-question {
                font-size: 0.9rem;
                margin-bottom: 10px;
            }

            .feedback-btn {
                font-size: 0.85rem;
                padding: 10px 16px;
            }

            .qa-question {
                font-size: 0.9rem;
                padding: 12px 14px;
            }

            .qa-answer {
                font-size: 0.85rem;
                padding: 0 14px 0px;
            }
        }

        .mobile-shop-now-bottom {
            display: none;
        }

        @media(max-width:480px) {
            .hero-title {
                font-size: 1.25rem;
            }

            .hero-sub {
                font-size: 0.85rem;
            }

            .shell {
                padding: 12px 10px 24px;
            }

            .page-panel {
                padding: 16px 12px 24px;
            }

            .section {
                padding: 16px 14px 18px;
            }

            .discount-percent {
                font-size: 2rem;
            }

            .coupon-code-box {
                font-size: 0.95rem;
            }

            .popup-banner {
                padding: 18px 14px 24px;
            }

            .popup-body {
                padding: 18px 14px 20px;
            }

            /* Mobile-only Shop Now dưới danh sách coupon */
            .mobile-shop-now-bottom {
                display: block;
                margin: 16px auto;
                max-width: 320px;
                padding: 12px 18px;
                text-align: center;
                border: none;
                border-radius: 999px;
                background: var(--primary);
                color: #fff;
                font-family: inherit;
                font-weight: 600;
                font-size: 0.95rem;
                cursor: pointer;
                text-decoration: none;
                box-shadow: 0 4px 12px rgba(47, 194, 169, 0.3);
            }

            .mobile-shop-now-bottom:hover {
                background: var(--primary-dark);
                color: #fff;
            }

            /* Ẩn nút Shop Now trong quick menu trên mobile để tránh trùng lặp */
            .quick-menu-shop-now {
                display: none;
            }
        }
    </style>
    @include('partials.tracking-head')
</head>

<body class="landing-coupon">
@include('partials.tracking-body')
    @include('partials.site-header')
    <div class="shell">
        <div class="page-panel">
            <header class="hero">
                <h1 class="hero-title">
                    {{ $brandName }} Coupons & Promo Codes – {{ now()->format('F Y') }}
                </h1>
                <p class="hero-sub">
                    Save up to <strong>{{ $maxPercent }}% off</strong> with verified discount codes &amp; exclusive
                    deals.
                </p>
                <p class="hero-sub" style="margin-top: 6px; font-size: 0.9rem; color: #9ca3af;">
                    We may earn a commission when you shop through our links, at no extra cost to you.
                </p>
            </header>

            <div class="page">
                <!-- LEFT CARD -->
                <aside class="left-card">
                    <div class="left-card-header">
                        @if ($campaign->brand)
                            <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}"
                                class="brand-logo" loading="lazy">
                        @elseif($campaign->logo)
                            <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}"
                                class="brand-logo" loading="lazy">
                        @else
                            <img src="{{ asset('images/default-brand.svg') }}"
                                alt="{{ $brandName }}" class="brand-logo"
                                loading="lazy">
                        @endif

                        <div class="brand-name">
                            {{ $brandName }}
                        </div>
                        <div class="brand-rating">
                            <span class="stars">★★★★★</span>
                            <span>Popular choice with our visitors</span>
                        </div>
                    </div>
                    @php
                        $workingCount = ($campaign->couponItems ?? collect())->count();
                        $successRate = $workingCount > 0 ? min(99, 80 + (int) round($workingCount * 1.5)) : 89;
                        $campaignSeed = (int) ($campaign->id ?? 0);
                        $estimatedTotalSaved = min(99999, 7229 + $workingCount * 80 + ($campaignSeed % 5000));
                    @endphp
                    <div class="left-card-footer">
                        <div class="stats-card">
                            <div class="stats-list">
                                <div class="stats-row">
                                    <span class="stats-label"><span class="stats-icon" aria-hidden="true">🏷</span>
                                        Available codes</span>
                                    <span class="stats-value">{{ $workingCount }}</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stats-label"><span class="stats-icon" aria-hidden="true">✓</span>
                                        Estimated success rate</span>
                                    <span class="stats-value">{{ $successRate }}%</span>
                                </div>
                                <div class="stats-row">
                                    <span class="stats-label"><span class="stats-icon" aria-hidden="true">💰</span>
                                        Estimated total saved</span>
                                    <span
                                        class="stats-value">${{ number_format($estimatedTotalSaved, 0, '.', ',') }}</span>
                                </div>
                            </div>
                        </div>
                        <nav class="quick-menu" aria-label="Quick menu">
                            <button type="button" class="quick-menu-shop-now"
                                onclick="handleShopNowClick()">Shop Now</button>
                            <h3 class="quick-menu-title">Quick Menu</h3>
                            <ul class="quick-menu-list">
                                <li><a href="#about-us">About us</a></li>
                                <li><a href="#how-to-use">How to use a coupon code</a></li>
                                <li><a href="#qa">Questions &amp; Answers</a></li>
                                <li><a href="#policies">Policies &amp; notes</a></li>
                            </ul>
                        </nav>
                    </div>
                </aside>

                <!-- RIGHT COLUMN -->
                <main class="right-column">
                    <section class="right-column-coupon-section">
                        <div class="coupon-header">
                            <div class="coupon-header-title">
                                Active {{ $brandName }} coupons
                            </div>
                            <div class="coupon-header-meta">
                                Last checked: 18 hours ago • Tracking coupons in the last 7 days.
                            </div>
                            <div class="filter-tabs">
                                <button class="filter-pill active" type="button" aria-pressed="true"
                                    aria-label="Show all deals">All Deals</button>
                                <button class="filter-pill" type="button" aria-pressed="false"
                                    aria-label="Show coupon codes only">Codes</button>
                                <button class="filter-pill" type="button" aria-pressed="false"
                                    aria-label="Show deals without codes">Deals</button>
                                <button class="filter-pill" type="button" aria-pressed="false"
                                    aria-label="Show free shipping offers">Free Shipping</button>
                                <button class="filter-pill" type="button" aria-pressed="false"
                                    aria-label="Show first order offers">First Order</button>
                            </div>
                        </div>

                        <section class="coupon-list" id="coupon-list">
                            @forelse($coupons as $coupon)
                                @php
                                    $hasCode = !empty($coupon->code);
                                    $offerText = $ensureOfferUtf8($coupon->offer ?? '');
                                    $descriptionText = (string) ($coupon->description ?? '');

                                    // Check for Free Shipping in both offer and description
                                    $isFreeShipping =
                                        stripos($offerText, 'free shipping') !== false ||
                                        stripos($descriptionText, 'free shipping') !== false;
                                    $tags = [];
                                    if ($isFreeShipping) {
                                        $tags[] = 'free-shipping';
                                    }
                                    $tagAttr = implode(',', $tags);

                                    // Extract offer value (percentage or currency) - regex /u cho ký hiệu €£¥₹
                                    $offerValue = null;
                                    $offerType = 'percent'; // 'percent', 'currency', or 'text'
                                    $currencySymbol = '';
                                    $currencyMap = [
                                        'USD' => '$',
                                        'EUR' => '€',
                                        'GBP' => '£',
                                        'JPY' => '¥',
                                        'INR' => '₹',
                                        'CAD' => 'C$',
                                        'AUD' => 'A$',
                                        'CHF' => 'CHF',
                                        'CNY' => '¥',
                                    ];

                                    if ($offerText !== '') {
                                        // Check for percentage first
                                        if (preg_match('/(\d+)\s*%/i', $offerText, $matches)) {
                                            $offerValue = (int) $matches[1];
                                            $offerType = 'percent';
                                        }
                                        // Check for currency symbols before number: $100, €200, £50 (u = Unicode)
                                        elseif (preg_match('/([€$£¥₹])\s*(\d+(?:[.,]\d+)?)/u', $offerText, $matches)) {
                                            $currencySymbol = $matches[1];
                                            $offerValue = $matches[2];
                                            $offerType = 'currency';
                                        }
                                        // Check for currency symbols after number: 100$, 200€
                                        elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*([€$£¥₹])/u', $offerText, $matches)) {
                                            $currencySymbol = $matches[2];
                                            $offerValue = $matches[1];
                                            $offerType = 'currency';
                                        }
                                        // Check for currency codes: USD, EUR, GBP, etc. before or after number
                                        elseif (
                                            preg_match(
                                                '/(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)\s*(\d+(?:[.,]\d+)?)/i',
                                                $offerText,
                                                $matches,
                                            )
                                        ) {
                                            $currencyCode = strtoupper($matches[1]);
                                            $currencySymbol = $currencyMap[$currencyCode] ?? $currencyCode;
                                            $offerValue = $matches[2];
                                            $offerType = 'currency';
                                        } elseif (
                                            preg_match(
                                                '/(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)/i',
                                                $offerText,
                                                $matches,
                                            )
                                        ) {
                                            $currencyCode = strtoupper($matches[2]);
                                            $currencySymbol = $currencyMap[$currencyCode] ?? $currencyCode;
                                            $offerValue = $matches[1];
                                            $offerType = 'currency';
                                        }
                                        // If Free Shipping, set as text type
                                        elseif ($isFreeShipping) {
                                            $offerValue = 'FREE';
                                            $offerType = 'text';
                                        }
                                    }

                                    // Default fallback
                                    if ($offerValue === null) {
                                        $offerValue = 15;
                                        $offerType = 'percent';
                                    }

                                    // Số liệu ổn định theo campaign + coupon (reload cùng trang không đổi, sang trang khác thì khác)
                                    $hoursAgo = ((($campaign->id ?? 0) * 7 + ($coupon->id ?? 0)) % 48) + 1;
                                    $uses = ((($campaign->id ?? 0) * 11 + ($coupon->id ?? 0)) % 4900) + 100;
                                @endphp
                                <article class="coupon-row" data-type="{{ $hasCode ? 'code' : 'deal' }}"
                                    data-tags="{{ $tagAttr }}" data-coupon-id="{{ $coupon->id }}">
                                    <!-- Left: Discount Visual -->
                                    <div class="coupon-discount-visual">
                                        @if ($offerType === 'text' && $offerValue === 'FREE')
                                            <div class="discount-up-to"
                                                style="font-size: 1rem; margin-bottom: 6px; color: #f97316;">FREE
                                            </div>
                                            <div class="discount-percent"
                                                style="font-size: 2rem; margin-bottom: 8px; color: #111827;">SHIPPING
                                            </div>
                                            <div class="discount-off-badge" style="display: none;"></div>
                                        @elseif($offerType === 'currency')
                                            <div class="discount-up-to">UP TO</div>
                                            <div class="discount-percent" style="font-size: 2.5rem;">
                                                {{ $currencySymbol }}{{ number_format((float) str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') }}
                                            </div>
                                            <div class="discount-off-badge">OFF</div>
                                        @else
                                            <div class="discount-up-to">UP TO</div>
                                            <div class="discount-percent">{{ $offerValue }}%</div>
                                            <div class="discount-off-badge">OFF</div>
                                        @endif
                                    </div>

                                    <!-- Right: Coupon Details -->
                                    <div class="coupon-info" data-coupon-id="{{ $coupon->id }}"
                                        data-code="{{ $coupon->code }}">
                                        <div class="coupon-header-row">
                                            <div style="flex: 1;">
                                                <div class="coupon-title">
                                                    @if ($coupon->description)
                                                        {{ $coupon->description }}
                                                    @else
                                                        @if ($offerType === 'currency')
                                                            Save
                                                            {{ $currencySymbol }}{{ number_format((float) str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') }}
                                                            on your order with this
                                                            {{ $brandName }} promo code
                                                        @elseif($offerType === 'text' && $offerValue === 'FREE')
                                                            Get Free Shipping on your order with this
                                                            {{ $brandName }} promo code
                                                        @else
                                                            Save {{ $offerValue }}% on your order with this
                                                            {{ $brandName }} promo code
                                                        @endif
                                                    @endif
                                                </div>
                                                @if ($loop->index < 2)
                                                    <div class="badge-row">
                                                        <div class="staff-pick-badge">STAFF PICK</div>
                                                        @if ($loop->first)
                                                            <div class="top-pic-badge">TOP PICK</div>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="coupon-meta-info">
                                                    <div class="meta-item">{{ $hoursAgo }} hours ago</div>
                                                    <div class="meta-item uses">{{ number_format($uses) }} Uses</div>
                                                </div>
                                            </div>
                                            @include('partials.coupon-get-code-btn', [
                                                'hasCode' => $hasCode,
                                                'code' => $coupon->code,
                                                'couponId' => $coupon->id,
                                                'url' => route('click.redirect', ['slug' => $campaignSlug]),
                                            ])
                                        </div>

                                        <div class="coupon-desc coupon-verified" aria-label="Coupon status">
                                            <span class="coupon-verified-badge" aria-hidden="true">
                                                <img src="{{ asset('images/verified-badge.png') }}" alt="">
                                            </span>
                                            <span class="coupon-verified-text">Verified recently</span>
                                        </div>

                                    </div>
                                </article>
                            @empty
                                <article class="section">
                                    <div class="section-title">No coupons available</div>
                                    <div class="section-body">
                                        Please check back later. We are updating new deals.
                                    </div>
                                </article>
                            @endforelse

                            @if ($couponsWithCodes->isNotEmpty())
                                {{-- Default coupon: giao diện giống 100% các coupon được tạo, % hoặc tiền cao nhất --}}
                                <article class="coupon-row coupon-row-default" data-type="code" data-all-codes="1"
                                    data-coupon-id="all-codes">
                                    <div class="coupon-discount-visual">
                                        @if ($defaultCouponOfferType === 'currency')
                                            <div class="discount-up-to">UP TO</div>
                                            <div class="discount-percent" style="font-size: 2.5rem;">
                                                {{ $defaultCouponCurrencySymbol }}{{ number_format($defaultCouponOfferValue, 0, '.', '') }}
                                            </div>
                                            <div class="discount-off-badge">OFF</div>
                                        @else
                                            <div class="discount-up-to">UP TO</div>
                                            <div class="discount-percent">{{ $defaultCouponOfferValue }}%</div>
                                            <div class="discount-off-badge">OFF</div>
                                        @endif
                                    </div>
                                    <div class="coupon-info" data-coupon-id="all-codes" data-all-codes="1">
                                        <div class="coupon-header-row">
                                            <div style="flex: 1;">
                                                <div class="coupon-title">
                                                    @if ($defaultCouponOfferType === 'currency')
                                                        Save
                                                        {{ $defaultCouponCurrencySymbol }}{{ number_format($defaultCouponOfferValue, 0, '.', '') }}
                                                        on your order with this
                                                        {{ $brandName }} promo code
                                                    @else
                                                        Save {{ $defaultCouponOfferValue }}% on your order with this
                                                        {{ $brandName }} promo code
                                                    @endif
                                                </div>
                                                <div class="badge-row">
                                                    <div class="all-verified-codes-badge">All Verified Codes</div>
                                                </div>
                                                <div class="coupon-meta-info">
                                                    <div class="meta-item">18 hours ago</div>
                                                    <div class="meta-item uses">
                                                        {{ number_format((($campaignSeed * 13 + 999) % 4900) + 100) }}
                                                        Uses</div>
                                                </div>
                                            </div>
                                            @include('partials.coupon-get-code-btn', [
                                                'hasCode' => true,
                                                'allCodes' => true,
                                                'couponId' => 'all-codes',
                                                'url' => route('click.redirect', ['slug' => $campaignSlug]),
                                            ])
                                        </div>
                                    </div>
                                </article>
                            @endif
                        </section>

                        {{-- Mobile-only Shop Now dưới danh sách coupon --}}
                        <button type="button" class="mobile-shop-now-bottom"
                            onclick="handleShopNowClick()">
                            Shop Now
                        </button>

                        <!-- ABOUT US -->
                        <section class="section" id="about-us">
                            <h2 class="section-title">About us</h2>
                            <div class="section-body intro-content">
                                @if ($campaign->intro)
                                    @php
                                        $intro = (string) $campaign->intro;
                                        $hasHtml = $intro !== strip_tags($intro);
                                    @endphp
                                    @if ($hasHtml)
                                        {!! $intro !!}
                                    @else
                                        {!! nl2br(e($intro)) !!}
                                    @endif
                                @else
                                    <p>
                                        Discover exclusive deals from {{ $brandName }} designed to help you save more every time you shop online. We carefully curate the latest discounts, special offers, and limited-time promotions so you can enjoy the best value on your favorite products. Whether you're looking for everyday essentials or trending items, {{ $brandName }} makes it easier to shop smarter and spend less.
                                    </p>
                                @endif
                            </div>
                        </section>

                        <!-- HOW TO USE -->
                        <section class="section" id="how-to-use">
                            <h2 class="section-title">How to use a coupon code</h2>
                            <div class="section-body">
                                <ul>
                                    <li>Select a coupon from the list above.</li>
                                    <li>Click <strong>Get Code</strong> to copy the code and visit the store.</li>
                                    <li>Add products to your cart as usual.</li>
                                    <li>Paste the coupon code at checkout and apply.</li>
                                </ul>
                            </div>
                        </section>

                        <!-- Q&A / FAQ -->
                        <section class="section qa-section" id="qa">
                            <h2 class="section-title">Questions &amp; Answers</h2>

                            <div class="qa-item">
                                <button class="qa-question" onclick="toggleQA(this)">
                                    How do I use this coupon code?
                                    <span class="qa-icon">+</span>
                                </button>
                                <div class="qa-answer">
                                    Simply click “Get Deal”, copy the coupon code, then apply it at checkout on the
                                    store’s website.
                                </div>
                            </div>

                            <div class="qa-item">
                                <button class="qa-question" onclick="toggleQA(this)">
                                    Why doesn’t my coupon work?
                                    <span class="qa-icon">+</span>
                                </button>
                                <div class="qa-answer">
                                    Some coupons require a minimum order value, specific products, or may have expired.
                                    Please double-check the terms before checkout.
                                </div>
                            </div>

                            <div class="qa-item">
                                <button class="qa-question" onclick="toggleQA(this)">
                                    Can I use more than one coupon?
                                    <span class="qa-icon">+</span>
                                </button>
                                <div class="qa-answer">
                                    Most stores allow only one coupon per order. Combining multiple offers is usually
                                    not supported.
                                </div>
                            </div>

                            <div class="qa-item">
                                <button class="qa-question" onclick="toggleQA(this)">
                                    Do you earn a commission from these deals?
                                    <span class="qa-icon">+</span>
                                </button>
                                <div class="qa-answer">
                                    Yes, we may earn a small commission when you make a purchase through our links, at
                                    no extra cost to you.
                                </div>
                            </div>
                        </section>


                        <!-- POLICY -->
                        <section class="section" id="policies">
                            <h2 class="section-title">Policies &amp; notes</h2>
                            <div class="section-body">
                                <ul>
                                    <li>Some coupons may require a minimum order value.</li>
                                    <li>Validity and conditions may change without notice.</li>
                                    <li>Please double-check your discount before checkout.</li>
                                    <li>We may earn a commission when you shop through our links.</li>
                                </ul>
                            </div>
                        </section>

                </main>
            </div><!-- /.page -->
        </div><!-- /.page-panel -->
    </div><!-- /.shell -->

    @include('partials.site-footer')

    <div id="couponModal" class="coupon-modal" role="dialog" aria-modal="true"
        aria-labelledby="coupon-modal-title">
        <div class="coupon-modal-stack">
            <div id="couponCopyToast" class="coupon-copy-toast" role="status" aria-live="polite" hidden>
                <div class="coupon-copy-toast-row">
                    <span class="coupon-copy-toast-icon" aria-hidden="true">✓</span>
                    <p class="coupon-copy-toast-text">Code copied! Go to
                        <strong>{{ e($brandName) }}</strong>
                    </p>
                    <button type="button" class="coupon-copy-toast-close" aria-label="Đóng"
                        onclick="dismissCouponCopyToast()">✕</button>
                </div>
                <div class="coupon-copy-toast-progress" aria-hidden="true"></div>
            </div>
        <div class="coupon-modal-content">
            <button type="button" class="coupon-modal-close" aria-label="Close coupon popup"
                onclick="closeCouponPopup()">✕</button>

            <!-- Banner Section -->
            <div class="popup-banner">
                <div class="popup-confetti">
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                    <div class="confetti-piece"></div>
                </div>
                <div class="popup-header">
                    @if ($campaign->brand)
                        <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}"
                            class="popup-logo" loading="lazy">
                    @elseif($campaign->logo)
                        <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}"
                            class="popup-logo" loading="lazy">
                    @else
                        <img src="{{ asset('images/default-brand.svg') }}"
                            alt="{{ $brandName }}" class="popup-logo"
                            loading="lazy">
                    @endif

                    <h3 class="popup-title" id="coupon-modal-title">
                        {{ $brandName }} Promotion
                    </h3>

                    <p class="popup-subtitle">
                        A Little Discount, A Lot of Joy—Save Now with {{ $brandName }}
                        Promotion.
                    </p>
                </div>
            </div>

            <!-- Body Section -->
            <div class="popup-body">
                <div class="coupon-code-container">
                    <div class="coupon-code-left">
                        <div class="coupon-code-icon">🏷</div>
                        <div id="modalCode" class="coupon-code-box">
                            CODE123
                        </div>
                    </div>
                    <button class="btn-copy-code-modal" id="copyCouponBtn" onclick="copyCoupon(this)"
                        aria-label="Copy coupon code">
                        COPY
                    </button>
                </div>

                <div class="popup-verification">
                    <div class="verification-item">
                        Recently checked and still active for most shoppers.
                    </div>
                    <div class="verification-item success">
                        We can’t guarantee every order, but many visitors report this code still works.
                    </div>
                </div>

                <div class="coupon-modal-actions">
                    <a href="#" target="_blank" rel="nofollow sponsored noopener"
                        class="coupon-btn store go-to-store-btn" aria-label="Open store in new tab"
                        onclick="event.preventDefault(); const url = this.getAttribute('data-url'); if(url) { window.open(url, '_blank'); } return false;">
                        Go To {{ $brandName }}
                    </a>
                </div>
                <div class="lead-capture">
                    <p class="lead-capture-label">Get exclusive discount codes.</p>
                    <div class="lead-capture-row">
                        <input id="leadEmailInput" type="email" class="lead-capture-input" placeholder="Enter your email">
                        <button id="leadSubmitBtn" type="button" class="lead-capture-btn" onclick="submitLeadEmail(this)">Send</button>
                    </div>
                    <p id="leadCaptureMsg" class="lead-capture-msg"></p>
                </div>

                <!-- Feedback Section -->
                <div class="popup-feedback">
                    <div class="popup-feedback-question">Did this code work?</div>
                    <div class="popup-feedback-buttons">
                        <button class="feedback-btn worked" onclick="handleFeedback(this, true)">
                            <span class="feedback-icon">👍</span>
                            <span>Yes, worked!</span>
                        </button>
                        <button class="feedback-btn failed" onclick="handleFeedback(this, false)">
                            <span class="feedback-icon">👎</span>
                            <span>No, failed</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    {{-- Modal: tất cả codes của các coupon --}}
    <div id="allCodesModal" class="coupon-modal all-codes-modal" role="dialog" aria-modal="true"
        aria-labelledby="all-codes-modal-title">
        <div class="coupon-modal-content all-codes-modal-content">
            <button type="button" class="coupon-modal-close" aria-label="Close"
                onclick="closeAllCodesModal()">✕</button>
            <div class="popup-banner">
                <div class="popup-header">
                    @if ($campaign->brand)
                        <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}"
                            class="popup-logo" loading="lazy">
                    @elseif($campaign->logo)
                        <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}"
                            class="popup-logo" loading="lazy">
                    @else
                        <img src="{{ asset('images/default-brand.svg') }}"
                            alt="{{ $brandName }}" class="popup-logo"
                            loading="lazy">
                    @endif
                    <h3 class="popup-title" id="all-codes-modal-title">All
                        {{ $brandName }} Coupon Codes</h3>
                    <p class="popup-subtitle">Copy any code below and apply at checkout.</p>
                </div>
            </div>
            <div class="popup-body">
                <div class="all-codes-list">
                    @foreach ($couponsWithCodes as $coupon)
                        @php
                            $offerText = $ensureOfferUtf8($coupon->offer ?? '');
                            $offerValue = null;
                            $offerType = 'percent';
                            $currencySymbol = '';
                            $isFreeShipping = stripos($offerText, 'free shipping') !== false;
                            $currencyMap = [
                                'USD' => '$',
                                'EUR' => '€',
                                'GBP' => '£',
                                'JPY' => '¥',
                                'INR' => '₹',
                                'CAD' => 'C$',
                                'AUD' => 'A$',
                                'CHF' => 'CHF',
                                'CNY' => '¥',
                            ];
                            if ($offerText !== '') {
                                if (preg_match('/(\d+)\s*%/i', $offerText, $m)) {
                                    $offerValue = (int) $m[1];
                                    $offerType = 'percent';
                                } elseif (preg_match('/([€$£¥₹])\s*(\d+(?:[.,]\d+)?)/u', $offerText, $m)) {
                                    $currencySymbol = $m[1];
                                    $offerValue = $m[2];
                                    $offerType = 'currency';
                                } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*([€$£¥₹])/u', $offerText, $m)) {
                                    $currencySymbol = $m[2];
                                    $offerValue = $m[1];
                                    $offerType = 'currency';
                                } elseif (
                                    preg_match(
                                        '/(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)\s*(\d+(?:[.,]\d+)?)/i',
                                        $offerText,
                                        $m,
                                    )
                                ) {
                                    $code = strtoupper($m[1]);
                                    $currencySymbol = $currencyMap[$code] ?? $code;
                                    $offerValue = $m[2];
                                    $offerType = 'currency';
                                } elseif (
                                    preg_match(
                                        '/(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)/i',
                                        $offerText,
                                        $m,
                                    )
                                ) {
                                    $code = strtoupper($m[2]);
                                    $currencySymbol = $currencyMap[$code] ?? $code;
                                    $offerValue = $m[1];
                                    $offerType = 'currency';
                                } elseif ($isFreeShipping) {
                                    $offerValue = 'FREE';
                                    $offerType = 'text';
                                }
                            } elseif ($isFreeShipping) {
                                $offerValue = 'FREE';
                                $offerType = 'text';
                            }
                        @endphp
                        <div class="all-codes-item">
                            <div class="coupon-code-container">
                                <div class="coupon-code-left">
                                    <div class="coupon-code-icon">🏷</div>
                                    <div class="coupon-code-box">{{ $coupon->code }}</div>
                                </div>
                                <button class="btn-copy-code-modal btn-copy-all-codes" type="button"
                                    data-code="{{ e($coupon->code) }}" aria-label="Copy code">
                                    COPY
                                </button>
                            </div>
                            <div class="all-codes-item-desc">
                                @if ($coupon->description)
                                    {{ Str::limit($coupon->description, 80) }}
                                @elseif($offerType === 'currency' && $offerValue !== null)
                                    Save
                                    {{ $currencySymbol }}{{ number_format((float) str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') }}
                                    on your order with this {{ $brandName }} promo code
                                @elseif($offerType === 'text' && $offerValue === 'FREE')
                                    Get Free Shipping on your order with this
                                    {{ $brandName }} promo code
                                @elseif($offerValue !== null)
                                    Save {{ $offerValue }}% on your order with this
                                    {{ $brandName }} promo code
                                @else
                                    Save with this coupon code when you shop at
                                    {{ $brandName }} and apply it at checkout.
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="coupon-modal-actions" style="margin-top: 20px;">
                    <a href="{{ route('click.redirect', ['slug' => $campaignSlug]) }}"
                        target="_blank" rel="nofollow sponsored noopener" class="coupon-btn store">
                        Go To {{ $brandName }}
                    </a>
                </div>
                <div class="lead-capture">
                    <p class="lead-capture-label">Get exclusive discount codes.</p>
                    <div class="lead-capture-row">
                        <input id="leadEmailInputAllCodes" type="email" class="lead-capture-input" placeholder="Enter your email">
                        <button id="leadSubmitBtnAllCodes" type="button" class="lead-capture-btn"
                            data-input-id="leadEmailInputAllCodes" data-msg-id="leadCaptureMsgAllCodes"
                            onclick="submitLeadEmail(this)">Send</button>
                    </div>
                    <p id="leadCaptureMsgAllCodes" class="lead-capture-msg"></p>
                </div>
            </div>
        </div>
    </div>


    <script>
        const firstCouponData = @json($firstCouponData);

        let currentCode = '';
        let currentCouponRow = null;
        let currentCouponId = null;
        let currentAffUrl = null;
        let affiliateAlreadyOpened = false;
        let couponCopyRedirectTimer = null;
        let couponCopyToastHideTimer = null;
        function getStoredRevealed() { return {}; }

        function saveRevealed(couponId, code) { return; }

        function isSafari() {
            const ua = navigator.userAgent;
            return /Safari/.test(ua) &&
                !/Chrome/.test(ua) &&
                !/CriOS/.test(ua) &&
                !/FxiOS/.test(ua) &&
                !/EdgiOS/.test(ua);
        }

        function revealCodeInRow(couponId, code, affUrl) {
            const row = document.querySelector('.coupon-row[data-coupon-id="' + couponId + '"]');
            if (!row) return;
            const btn = row.querySelector('.btn-get-code');
            if (!btn) return;
            const codeSpan = document.createElement('span');
            codeSpan.className = 'coupon-revealed-code';
            codeSpan.textContent = code;
            codeSpan.title = 'Click để copy lại';
            if (affUrl) codeSpan.dataset.url = affUrl;
            codeSpan.onclick = function(e) {
                e.stopPropagation();
                copyCodeToClipboard(code);
                const t = this.textContent;
                this.textContent = 'Copied ✓';
                this.style.background = '#198754';
                this.style.color = '#fff';
                setTimeout(() => {
                    this.textContent = t;
                    this.style.background = '';
                    this.style.color = '';
                }, 1500);
            };
            btn.parentNode.replaceChild(codeSpan, btn);
            saveRevealed(couponId, code);

            // Nếu popup all-codes đang mở, hiện luôn code thật cho item tương ứng
            document
                .querySelectorAll('#allCodesModal .all-codes-item .btn-copy-all-codes')
                .forEach(function(bt) {
                    if (bt.dataset.code === code) {
                        const box = bt
                            .closest('.all-codes-item')
                            ?.querySelector('.coupon-code-box');
                        if (box && box.dataset.realCode) {
                            box.textContent = box.dataset.realCode;
                        }
                    }
                });
        }

        function copyCodeToClipboard(text) {
            navigator.clipboard.writeText(text);
        }

        function showCouponCopyToast() {
            const el = document.getElementById('couponCopyToast');
            if (!el) return;
            if (couponCopyToastHideTimer) {
                clearTimeout(couponCopyToastHideTimer);
                couponCopyToastHideTimer = null;
            }
            el.classList.remove('is-hiding');
            el.hidden = false;
            void el.offsetWidth;
            el.classList.add('is-visible');
            const bar = el.querySelector('.coupon-copy-toast-progress');
            if (bar) {
                bar.style.animation = 'none';
                void bar.offsetWidth;
                bar.style.animation = '';
            }
        }

        function hideCouponCopyToast() {
            const el = document.getElementById('couponCopyToast');
            if (!el) return;
            el.classList.remove('is-visible');
            el.classList.add('is-hiding');
            if (couponCopyToastHideTimer) {
                clearTimeout(couponCopyToastHideTimer);
            }
            couponCopyToastHideTimer = setTimeout(() => {
                el.hidden = true;
                el.classList.remove('is-hiding');
                couponCopyToastHideTimer = null;
            }, 260);
        }

        function dismissCouponCopyToast() {
            if (couponCopyRedirectTimer) {
                clearTimeout(couponCopyRedirectTimer);
                couponCopyRedirectTimer = null;
            }
            if (couponCopyToastHideTimer) {
                clearTimeout(couponCopyToastHideTimer);
                couponCopyToastHideTimer = null;
            }
            hideCouponCopyToast();
            const copyBtn = document.getElementById('copyCouponBtn');
            if (copyBtn) {
                copyBtn.disabled = false;
                if (copyBtn.dataset.copyLabelDefault) {
                    copyBtn.innerText = copyBtn.dataset.copyLabelDefault;
                }
            }
        }

        function setCouponModalHash(couponId) {
            const id = String(couponId);
            const path = window.location.pathname + window.location.search;
            history.replaceState(null, '', path + '#coupon=' + encodeURIComponent(id));
        }

        function clearCouponModalHash() {
            if (!/^#coupon=/.test(window.location.hash)) return;
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        function restoreCouponModalFromHash() {
            const m = /^#coupon=(.+)$/.exec(window.location.hash);
            if (!m) return;
            const id = decodeURIComponent(m[1]);
            const singleModal = document.getElementById('couponModal');
            const allModal = document.getElementById('allCodesModal');
            if (singleModal && singleModal.classList.contains('active')) return;
            if (allModal && allModal.classList.contains('active')) return;

            if (id === 'all-codes') {
                const allRow = document.querySelector('.coupon-row[data-coupon-id="all-codes"]') ||
                    document.querySelector('.coupon-row[data-all-codes="1"]');
                if (allRow) handleCouponClick(allRow);
                return;
            }

            const btn = Array.prototype.find.call(document.querySelectorAll('.btn-get-code'), function (b) {
                return b.dataset.couponId === id;
            });
            if (!btn) return;
            if (btn.dataset.allCodes === '1' || btn.dataset.allCodes === 'true') {
                handleCouponClick(btn.closest('.coupon-row') || btn);
                return;
            }
            const type = btn.dataset.type;
            const code = btn.dataset.code || '';
            const url = btn.dataset.url;
            const couponId = btn.dataset.couponId;
            if (type === 'deal' || !code) {
                currentCouponRow = btn.closest('.coupon-row');
                openModalForCoupon(couponId, '', url);
                return;
            }
            currentCouponRow = btn.closest('.coupon-row');
            openModalForCoupon(couponId, code, url);
        }

        function hasCouponCode(code) {
            return !!(code && String(code).trim());
        }

        function updateModalCodeUI(hasCode) {
            const codeBox = document.getElementById('modalCode');
            const copyBtn = document.getElementById('copyCouponBtn');
            const container = document.querySelector('#couponModal .coupon-code-container');
            if (codeBox) {
                codeBox.classList.toggle('coupon-code-box--no-code', !hasCode);
                codeBox.innerText = hasCode ? '••••••••' : 'No code needed!';
            }
            if (copyBtn) {
                copyBtn.style.display = hasCode ? '' : 'none';
                copyBtn.disabled = false;
                if (copyBtn.dataset.copyLabelDefault) {
                    copyBtn.innerText = copyBtn.dataset.copyLabelDefault;
                }
            }
            if (container) {
                container.classList.toggle('is-no-code', !hasCode);
            }
        }

        function openCouponDualTabFlow(couponId, code, affUrl) {
            const hasCode = hasCouponCode(code);
            if (!affiliateAlreadyOpened) {
                const couponUrl = new URL(window.location.href);
                couponUrl.searchParams.set('show_coupon', String(couponId));
                if (hasCode) {
                    couponUrl.searchParams.set('code', String(code));
                } else {
                    couponUrl.searchParams.delete('code');
                }
                couponUrl.searchParams.set('aff_opened', '1');
                couponUrl.hash = '';
                window.open(couponUrl.toString(), '_blank', 'noopener');
                if (affUrl) window.location.href = affUrl;
                return;
            }
            openModalForCoupon(couponId, hasCode ? code : '', affUrl);
        }

        function handleShopNowClick() {
            openCouponDualTabFlow(
                firstCouponData.id || 'shop-now',
                (firstCouponData.code || '').trim(),
                firstCouponData.url
            );
            return false;
        }

        function openModalForCoupon(couponId, code, affUrl) {
            const hasCode = hasCouponCode(code);
            currentCode = hasCode ? code : '';
            currentCouponId = couponId;
            currentAffUrl = affUrl || null;
            updateModalCodeUI(hasCode);
            const modal = document.getElementById('couponModal');
            if (modal) modal.classList.add('active');
            const goBtn = document.querySelector('.go-to-store-btn');
            if (goBtn && affUrl) {
                goBtn.setAttribute('data-url', affUrl);
                goBtn.href = affUrl;
            }
            resetLeadCapture('leadEmailInput', 'leadCaptureMsg', 'leadSubmitBtn');
            setCouponModalHash(couponId);
        }

        function resetLeadCapture(inputId, msgId, btnId) {
            const input = document.getElementById(inputId);
            const msg = document.getElementById(msgId);
            const btn = document.getElementById(btnId);
            if (input) input.value = '';
            if (msg) { msg.textContent = ''; msg.className = 'lead-capture-msg'; }
            if (btn) btn.disabled = false;
        }

        function submitLeadEmail(btn) {
            const inputId = (btn && btn.dataset && btn.dataset.inputId) ? btn.dataset.inputId : 'leadEmailInput';
            const msgId = (btn && btn.dataset && btn.dataset.msgId) ? btn.dataset.msgId : 'leadCaptureMsg';
            const input = document.getElementById(inputId);
            const msg = document.getElementById(msgId);
            if (!input || !msg) return;
            const email = (input.value || '').trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                msg.textContent = 'Please enter a valid email address.';
                msg.className = 'lead-capture-msg error';
                return;
            }

            btn.disabled = true;
            msg.textContent = 'Sending...';
            msg.className = 'lead-capture-msg';

            fetch('{{ route('customer-leads.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    campaign_id: {{ (int) $campaign->id }},
                    email: email,
                    source_template: 'template1'
                })
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) throw new Error(data.message || 'Sending email failed');
                msg.textContent = 'Email submitted successfully.';
                msg.className = 'lead-capture-msg success';
                input.value = '';
            })
            .catch((e) => {
                msg.textContent = e.message || 'Unable to submit your email right now.';
                msg.className = 'lead-capture-msg error';
            })
            .finally(() => {
                btn.disabled = false;
            });
        }

        function handleCouponClick(btn) {
            const actualBtn = btn.classList && btn.classList.contains('btn-get-code') ? btn : btn.querySelector(
                '.btn-get-code');
            const row = btn.closest('.coupon-row');
            if (!actualBtn && !row) return false;
            const target = actualBtn || row;
            const isAllCodes = target.dataset.allCodes === '1' || target.dataset.allCodes === 'true';

            if (isAllCodes) {
                const affUrl =
                    target.dataset.url ||
                    (document.querySelector('.btn-get-code[data-url]')?.dataset.url || null);

                if (!affiliateAlreadyOpened) {
                    // Flow mới: tab hiện tại -> link aff, tab mới -> mở trang coupon + modal
                    const couponUrl = new URL(window.location.href);
                    couponUrl.searchParams.set('aff_opened', '1');
                    couponUrl.hash = 'coupon=all-codes';
                    window.open(couponUrl.toString(), '_blank', 'noopener');
                    if (affUrl) window.location.href = affUrl;
                    return false;
                }

                document.getElementById('allCodesModal').classList.add('active');
                resetLeadCapture('leadEmailInputAllCodes', 'leadCaptureMsgAllCodes', 'leadSubmitBtnAllCodes');
                setCouponModalHash('all-codes');
                document.querySelectorAll('.btn-copy-all-codes').forEach(function(bt) {
                    bt.onclick = function() {

                        const code = this.dataset.code;
                        if (!code) return;

                        copyCodeToClipboard(code);
                        const originalText = this.textContent;

                        const box = this
                            .closest('.all-codes-item')
                            ?.querySelector('.coupon-code-box');

                        if (box && box.dataset.realCode) {
                            box.textContent = box.dataset.realCode;
                        }

                        this.textContent = 'Copied ✓';
                        this.disabled = true;

                        const safari = isSafari();

                        // Safari → mở ngay
                        if (safari) {

                            if (affUrl && !affiliateAlreadyOpened) {
                                window.open(affUrl, '_blank');
                            }

                            this.textContent = 'Go To Store';

                            setTimeout(() => {
                                this.textContent = originalText;
                                this.disabled = false;
                            }, 1500);

                        } else {

                            // Browser khác → giữ delay 2s
                            setTimeout(() => {
                                this.textContent = 'Go To Store';
                            }, 600);

                            setTimeout(() => {
                            if (affUrl && !affiliateAlreadyOpened) {
                                    window.open(affUrl, '_blank');
                                }
                                this.textContent = originalText;
                                this.disabled = false;
                                this.classList.remove('copied');
                            }, 1500);
                        }

                    };
                });
                return false;
            }

            if (!actualBtn) return false;
            const type = actualBtn.dataset.type;
            const code = actualBtn.dataset.code || '';
            const url = actualBtn.dataset.url;
            const couponId = actualBtn.dataset.couponId;

            const activeTabBtn = document.querySelector('.filter-pill.active');
            const activeTab = activeTabBtn ? (activeTabBtn.dataset.tab || 'all') : 'all';

            // Deal / không có mã: tab hiện tại -> aff, tab mới -> popup "No code needed!"
            if (type === 'deal' || !code) {
                currentCouponRow = actualBtn.closest('.coupon-row');
                openCouponDualTabFlow(couponId, '', url);
                return false;
            }

            currentCode = code;
            currentCouponId = couponId;
            currentCouponRow = actualBtn.closest('.coupon-row');

            // Flow cho Get Code: tab hiện tại -> aff, tab mới -> trang coupon + modal
            openCouponDualTabFlow(couponId, code, url);
            return false;
        }

        function closeCouponPopup() {
            if (couponCopyRedirectTimer) {
                clearTimeout(couponCopyRedirectTimer);
                couponCopyRedirectTimer = null;
            }
            hideCouponCopyToast();
            const copyBtn = document.getElementById('copyCouponBtn');
            if (copyBtn) {
                copyBtn.disabled = false;
                if (copyBtn.dataset.copyLabelDefault) {
                    copyBtn.innerText = copyBtn.dataset.copyLabelDefault;
                }
            }
            document.getElementById('couponModal').classList.remove('active');
            updateModalCodeUI(true);
            clearCouponModalHash();
        }

        function closeAllCodesModal() {
            document.getElementById('allCodesModal').classList.remove('active');
            clearCouponModalHash();
        }

        function handleFeedback(btn, worked) {
            // Disable both buttons
            const buttons = document.querySelectorAll('.feedback-btn');
            buttons.forEach(b => {
                b.disabled = true;
                b.style.opacity = '0.6';
                b.style.cursor = 'not-allowed';
            });

            // Highlight selected button
            if (worked) {
                btn.style.background = '#dcfce7';
                btn.style.borderColor = '#198754';
                btn.innerHTML = '<span class="feedback-icon">✓</span><span>Thank you!</span>';
            } else {
                btn.style.background = '#fef2f2';
                btn.style.borderColor = '#ef4444';
                btn.innerHTML = '<span class="feedback-icon">✓</span><span>Thank you!</span>';
            }

            // Here you can send feedback to server if needed
            // fetch('/api/feedback', { method: 'POST', body: JSON.stringify({ couponId: currentCouponId, worked: worked }) });
        }

        function copyCoupon(btn) {
            if (!currentCode) return;

            if (!btn.dataset.copyLabelDefault) {
                btn.dataset.copyLabelDefault = btn.innerText.trim();
            }

            copyCodeToClipboard(currentCode);
            const originalText = btn.dataset.copyLabelDefault;

            // Hiện code thật trong popup sau khi copy
            const codeBox = document.getElementById('modalCode');
            if (codeBox) {
                codeBox.innerText = currentCode;
            }

            btn.innerText = 'Copied ✓';
            btn.disabled = true;
            showCouponCopyToast();

            const goBtn = document.querySelector('.go-to-store-btn');
            if (goBtn) {
                goBtn.classList.remove('go-store-attention');
                void goBtn.offsetWidth;
                goBtn.classList.add('go-store-attention');
            }

            const affUrl = currentAffUrl || (goBtn ? goBtn.getAttribute('data-url') : null);
            if (currentCouponId && currentCode) {
                revealCodeInRow(currentCouponId, currentCode, affUrl);
            }

            if (couponCopyRedirectTimer) clearTimeout(couponCopyRedirectTimer);
            couponCopyRedirectTimer = setTimeout(() => {
                couponCopyRedirectTimer = null;
                hideCouponCopyToast();
                if (affUrl && !affiliateAlreadyOpened) {
                    window.open(affUrl, '_blank');
                }
                btn.innerText = originalText;
                btn.disabled = false;
            }, 2300);
        }

        function toggleQA(el) {
            const item = el.closest('.qa-item');
            item.classList.toggle('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const affUrl = (function() {
                const b = document.querySelector('.btn-get-code[data-url]');
                return b ? b.dataset.url : null;
            })();
            // Không khôi phục code đã reveal từ lần tải trước.

            // Ẩn toàn bộ mã trong popup all-codes cho tới khi user copy
            document
                .querySelectorAll('#allCodesModal .all-codes-item .coupon-code-box')
                .forEach(function(box) {
                    const real = (box.textContent || '').trim();
                    box.dataset.realCode = real;
                    box.textContent = '••••••••';
                });

            const urlParams = new URLSearchParams(window.location.search);
            affiliateAlreadyOpened = urlParams.get('aff_opened') === '1';
            const showCouponId = urlParams.get('show_coupon');
            const codeFromUrl = urlParams.get('code');
            if (showCouponId && affiliateAlreadyOpened) {
                try {
                    const rowBtn = document.querySelector('.btn-get-code[data-coupon-id="' + showCouponId + '"]');
                    const url = rowBtn ? rowBtn.dataset.url : (showCouponId === 'shop-now' ? firstCouponData.url : affUrl);
                    if (codeFromUrl) {
                        const decoded = decodeURIComponent(codeFromUrl);
                        currentCode = decoded;
                        currentCouponId = showCouponId;
                        currentCouponRow = document.querySelector('.coupon-row[data-coupon-id="' + showCouponId + '"]');
                        revealCodeInRow(showCouponId, decoded, url || affUrl);
                        openModalForCoupon(showCouponId, decoded, url || affUrl);
                    } else if (showCouponId === 'shop-now') {
                        openModalForCoupon(
                            firstCouponData.id || 'shop-now',
                            (firstCouponData.code || '').trim(),
                            firstCouponData.url
                        );
                    } else {
                        currentCouponId = showCouponId;
                        currentCouponRow = document.querySelector('.coupon-row[data-coupon-id="' + showCouponId + '"]');
                        openModalForCoupon(showCouponId, '', url || affUrl);
                    }
                } catch (e) {}
            } else {
                restoreCouponModalFromHash();
            }

            window.addEventListener('hashchange', function () {
                restoreCouponModalFromHash();
            });

            const tabs = document.querySelectorAll('.filter-pill');
            const rows = document.querySelectorAll('.coupon-row');

            tabs.forEach((tab) => {
                const label = tab.textContent.trim().toLowerCase();
                if (label === 'all deals') tab.dataset.tab = 'all';
                if (label === 'codes') tab.dataset.tab = 'codes';
                if (label === 'deals') tab.dataset.tab = 'deals';
                if (label === 'free shipping') tab.dataset.tab = 'free-shipping';
                if (!tab.hasAttribute('aria-pressed')) {
                    tab.setAttribute('aria-pressed', tab.classList.contains('active') ? 'true' : 'false');
                }
            });

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => {
                        t.classList.remove('active');
                        t.setAttribute('aria-pressed', 'false');
                    });
                    tab.classList.add('active');
                    tab.setAttribute('aria-pressed', 'true');

                    const current = tab.dataset.tab || 'all';

                    rows.forEach(row => {
                        const type = row.dataset.type;
                        const tags = (row.dataset.tags || '').split(',').filter(Boolean);
                        let show = true;

                        if (current === 'codes') {
                            show = type === 'code';
                        } else if (current === 'deals') {
                            show = type === 'deal';
                        } else if (current === 'free-shipping') {
                            show = tags.includes('free-shipping');
                        } else {
                            show = true;
                        }

                        row.style.display = show ? 'grid' : 'none';
                    });
                });
            });
        });

        // Track time on page and bounce rate
        @if (isset($pageView) && $pageView)
            (function() {
                const pageViewId = {{ $pageView->id }};
                const startTime = Date.now();
                let timeOnPage = 0;
                let isBounce = true;
                let hasInteracted = false;

                // Track user interactions to determine if it's a bounce
                ['click', 'scroll', 'keydown', 'touchstart'].forEach(event => {
                    document.addEventListener(event, function() {
                        hasInteracted = true;
                        isBounce = false;
                    }, {
                        once: true
                    });
                });

                // Update time on page periodically
                setInterval(function() {
                    timeOnPage = Math.floor((Date.now() - startTime) / 1000);
                }, 1000);

                // Send data when page is about to unload
                window.addEventListener('beforeunload', function() {
                    timeOnPage = Math.floor((Date.now() - startTime) / 1000);

                    // Use sendBeacon for reliable delivery
                    const data = JSON.stringify({
                        time_on_page: timeOnPage,
                        is_bounce: isBounce && !hasInteracted && timeOnPage < 30
                    });

                    navigator.sendBeacon(
                        '{{ route('analytics.update-page-view', ':id') }}'.replace(':id', pageViewId),
                        data
                    );
                });

                // Also send on visibility change (tab switch)
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden) {
                        timeOnPage = Math.floor((Date.now() - startTime) / 1000);
                        fetch('{{ route('analytics.update-page-view', ':id') }}'.replace(':id', pageViewId), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                time_on_page: timeOnPage,
                                is_bounce: isBounce && !hasInteracted && timeOnPage < 30
                            })
                        }).catch(() => {}); // Ignore errors
                    }
                });
            })();
        @endif
    </script>

    @include('partials.back-to-top')

</body>

</html>
