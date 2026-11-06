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
            if ($text === '') return $text;
            if (mb_check_encoding($text, 'UTF-8') && mb_strpos($text, "\xEF\xBF\xBD") === false) {
                return $text;
            }
            $decoded = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            return $decoded !== false ? $decoded : $text;
        };

        $coupons = $campaign->couponItems ?? collect();
        // Sắp xếp coupon theo thứ tự tạo (cũ -> mới)
        $coupons = $coupons
            ->sortBy(fn ($c) => $c->created_at ?? $c->id ?? 0)
            ->values();
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
                    $codeToSymbol = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'INR' => '₹', 'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF', 'CNY' => '¥'];
                    $maxCurrencySymbol = $codeToSymbol[strtoupper($m[1])] ?? $m[1];
                }
            } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)/i', $offerText, $m)) {
                $val = (float) str_replace([',', ' '], ['', ''], $m[1]);
                if ($val > $maxCurrencyValue) {
                    $maxCurrencyValue = $val;
                    $codeToSymbol = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 'INR' => '₹', 'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF', 'CNY' => '¥'];
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

        $couponsWithCodes = $coupons->filter(fn ($c) => !empty($c->code))->values();
        
        $campaignSlug = $campaign->slug;

        $brandName = $campaign->title;
        $rawIntro = (string) ($campaign->subtitle ?? $campaign->intro ?? '');
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
    <meta name="robots" content="index, follow">
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @include('partials.site-chrome-styles')
    @include('partials.landing-magazine-head')
    @if(config('app.ga4_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga4_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('app.ga4_id') }}');
    </script>
    @endif

    @include('partials.peel-sticker-styles')

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --t2-primary: #2563eb;
            --t2-primary-dark: #1d4ed8;
            --t2-primary-soft: #eff6ff;
            --t2-banner: #1e3a8a;
            --t2-banner-dark: #0f172a;
            --t2-text: #1e293b;
            --t2-text-muted: #64748b;
            --t2-bg: #f8fafc;
            --t2-card: #ffffff;
            --t2-border: #e2e8f0;
        }
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            color: var(--t2-text);
            background: var(--t2-bg);
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; transition: color 0.2s ease; }

        /* Store banner - dark navy/charcoal */
        .t2-store-banner {
            background: linear-gradient(180deg, var(--t2-banner) 0%, var(--t2-banner-dark) 100%);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .t2-banner-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .t2-banner-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            background: #fff;
            border-radius: 8px;
            padding: 6px;
        }
        .t2-banner-name {
            font-weight: 700;
            font-size: 1.2rem;
            color: #fff;
        }
        .t2-banner-cta {
            display: inline-flex;
            align-items: center;
            padding: 10px 24px;
            background: var(--t2-primary);
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }
        .t2-banner-cta:hover {
            background: var(--t2-primary-dark);
            transform: translateY(-1px);
            color: #fff;
        }

        /* Full-width main */
        .t2-main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px 24px 50px;
        }

        /* Hero */
        .t2-hero {
            margin-bottom: 24px;
        }
        .t2-hero-title {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 8px;
            color: var(--t2-text);
        }
        .t2-hero-sub {
            font-size: 0.95rem;
            color: var(--t2-text-muted);
        }
        .t2-hero-sub strong { color: var(--t2-primary); font-weight: 700; }
        .t2-hero-disclosure {
            margin-top: 6px;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Quick menu - horizontal anchor links */
        .t2-quick-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 20px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--t2-border);
        }
        .t2-quick-nav a {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--t2-text-muted);
            padding: 6px 0;
            border-bottom: 2px solid transparent;
        }
        .t2-quick-nav a:hover { color: var(--t2-primary); border-bottom-color: var(--t2-primary); }

        /* Stats bar - compact above coupon list */
        .t2-stats-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px 24px;
            padding: 14px 18px;
            background: var(--t2-card);
            border: 1px solid var(--t2-border);
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .t2-stats-item { display: flex; align-items: center; gap: 8px; }
        .t2-stats-value { font-weight: 700; color: var(--t2-text); }
        .t2-stats-label { color: var(--t2-text-muted); }

        /* Coupon section header */
        .t2-coupon-header-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }
        .t2-coupon-header-meta { font-size: 0.85rem; color: var(--t2-text-muted); margin-bottom: 12px; }

        /* Filter pills */
        .t2-filter-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .filter-pill {
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.85rem;
            border: 1px solid var(--t2-border);
            background: var(--t2-card);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-family: inherit;
        }
        .filter-pill:hover { border-color: var(--t2-primary); background: var(--t2-primary-soft); }
        .filter-pill.active {
            background: var(--t2-primary);
            border-color: var(--t2-primary);
            color: #fff;
            font-weight: 600;
        }

        /* Coupon rows - table-style, flat */
        .t2-coupon-list { display: flex; flex-direction: column; gap: 0; }
        .coupon-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 20px;
            background: var(--t2-card);
            border: 1px solid var(--t2-border);
            border-top: none;
            transition: background 0.2s;
        }
        .coupon-row:first-child { border-top: 1px solid var(--t2-border); }
        .coupon-row:hover { background: #fefefe; }
        .t2-coupon-content { flex: 1; min-width: 0; }
        .coupon-title {
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.4;
            color: var(--t2-text);
            margin-bottom: 6px;
        }
        .staff-pick-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--t2-primary);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .staff-pick-badge::before { content: '⭐'; font-size: 0.6rem; }
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
            background: #0f172a;
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .top-pic-badge::before { content: '🔥'; font-size: 0.6rem; }
        .all-verified-codes-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--t2-primary);
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .all-verified-codes-badge::before { content: '✓'; font-size: 0.7rem; font-weight: 900; }
        .coupon-meta-info {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.8rem;
            color: var(--t2-text-muted);
        }
        .meta-item { display: flex; align-items: center; gap: 5px; }
        .meta-item::before { content: '✓'; color: var(--t2-primary); font-weight: 700; }
        .meta-item.uses::before { content: '👥'; }
        .coupon-desc.coupon-verified {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #16a34a;
        }
        .coupon-verified-badge { width: 20px; height: 20px; flex-shrink: 0; }
        .coupon-verified-badge img { width: 100%; height: 100%; object-fit: contain; }
        .coupon-revealed-code {
            display: inline-flex;
            align-items: center;
            padding: 0px 16px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: ui-monospace, monospace;
            letter-spacing: 0.05em;
            background: var(--t2-primary-soft);
            color: var(--t2-primary-dark);
            border: 1px dashed var(--t2-primary);
            border-radius: 6px;
            white-space: nowrap;
            cursor: pointer;
            flex-shrink: 0;
        }

        /* Sections - flat cards */
        .section {
            background: var(--t2-card);
            border: 1px solid var(--t2-border);
            padding: 24px 28px;
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 14px;
            color: var(--t2-text);
        }
        .section-body {
            font-size: 0.85rem;
            color: var(--t2-text-muted);
            line-height: 1.75;
        }
        .section-body ul { padding-left: 18px; margin: 6px 0; }
        .intro-content { line-height: 1.75; color: var(--t2-text-muted); }
        .intro-content p { margin-bottom: 1rem; color: var(--t2-text); }
        .intro-content p:last-child { margin-bottom: 0; }
        .intro-content a { color: var(--t2-primary); text-decoration: underline; }
        .intro-content a:hover { color: var(--t2-primary-dark); }

        /* Q&A */
        .qa-item {
            border: 1px solid var(--t2-border);
            margin-bottom: 8px;
            overflow: hidden;
            background: var(--t2-card);
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
            color: var(--t2-text);
            font-family: inherit;
        }
        .qa-icon { font-size: 1.2rem; transition: transform 0.3s ease; }
        .qa-answer {
            max-height: 0;
            overflow: hidden;
            padding: 0 16px;
            font-size: 0.85rem;
            color: var(--t2-text-muted);
            transition: all 0.35s ease;
        }
        .qa-item.active .qa-answer { max-height: 200px; padding: 10px 16px 16px; }
        .qa-item.active .qa-icon { transform: rotate(45deg); }

        /* Modal - dark header, orange accents */
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
            transition: opacity 0.25s ease;
        }
        .coupon-modal.active { opacity: 1; pointer-events: auto; }
        .coupon-modal-stack {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            max-width: 100%;
            max-height: calc(100vh - 24px);
            padding: 0 12px;
            box-sizing: border-box;
        }
        .coupon-copy-toast[hidden] { display: none !important; }
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
        .coupon-copy-toast-icon { flex-shrink: 0; font-weight: 700; font-size: 1.05rem; line-height: 1.35; margin-top: 1px; }
        .coupon-copy-toast-text {
            flex: 1;
            margin: 0;
            text-align: center;
            line-height: 1.45;
            min-width: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .coupon-copy-toast-text strong { font-weight: 700; color: #fff; }
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
        .coupon-copy-toast-close:hover { background: rgba(255, 255, 255, 0.12); }
        @media (max-width: 480px) {
            .coupon-copy-toast { width: calc(100vw - 1.5rem); max-width: calc(100vw - 1.5rem); font-size: 1rem; }
            .coupon-copy-toast-row { padding: 10px 10px 12px 12px; gap: 8px; }
        }
        .coupon-copy-toast-progress {
            width: 100%;
            height: 3px;
            background: #22c55e;
            transform-origin: left center;
            animation: couponCopyToastProgress 2s linear forwards;
        }
        @keyframes couponCopyToastProgress {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
        .coupon-modal-content {
            position: relative;
            background: var(--t2-card);
            width: 100%;
            max-width: 580px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: t2PopupScale 0.3s ease;
            max-height: calc(100vh - 24px);
            display: flex;
            flex-direction: column;
        }
        .t2-popup-banner {
            background: linear-gradient(180deg, var(--t2-banner) 0%, var(--t2-banner-dark) 100%);
            padding: 28px 24px 32px;
            text-align: center;
            position: relative;
        }
        .t2-popup-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #fff;
            padding: 10px;
            margin: 0 auto 12px;
            object-fit: contain;
        }
        .t2-popup-title { font-size: 1.2rem; font-weight: 700; color: #fff; margin: 0; }
        .t2-popup-subtitle { font-size: 0.9rem; color: rgba(255,255,255,0.9); margin-top: 8px; }
        .t2-popup-banner { flex-shrink: 0; }
        .t2-popup-body {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        .coupon-code-container {
            background: var(--t2-banner);
            border-radius: 8px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .coupon-code-left { display: flex; align-items: center; gap: 10px; flex: 1; }
        .coupon-code-icon {
            width: 28px;
            height: 28px;
            background: var(--t2-primary);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: 0.8rem;
        }
        .coupon-code-box {
            background: transparent;
            border: none;
            padding: 0;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            font-family: ui-monospace, monospace;
        }
        .btn-copy-code-modal {
            background: var(--t2-primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-copy-code-modal:hover {
            background: var(--t2-primary-dark);
            transform: translateY(-1px);
        }
        .coupon-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            color: #fff;
            z-index: 10;
        }
        .coupon-modal-close:hover { background: rgba(255,255,255,0.25); }
        .popup-verification {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--t2-text-muted);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--t2-border);
            flex-wrap: wrap;
            gap: 8px;
        }
        .verification-item { display: flex; align-items: center; gap: 6px; }
        .verification-item::before { content: '✓'; color: var(--t2-primary); font-weight: 700; }
        .verification-item.success::before { content: '●'; color: #16a34a; }
        .coupon-modal-actions { margin-top: 20px; }
        .coupon-btn {
            width: 100%;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
            display: block;
            font-family: inherit;
        }
        .coupon-btn.store {
            background: var(--t2-primary);
            color: #fff;
        }
        .coupon-btn.store:hover {
            background: var(--t2-primary-dark);
            transform: translateY(-1px);
            color: #fff;
        }
        .popup-feedback { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--t2-border); }
        .popup-feedback-question { text-align: center; font-size: 0.95rem; font-weight: 600; margin-bottom: 12px; }
        .popup-feedback-buttons { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .lead-capture {
            margin-top: 14px;
        }
        .lead-capture-label {
            margin: 0 0 8px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--t2-text);
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
            border: 1px solid var(--t2-border);
            padding: 0 12px;
            font-size: 0.9rem;
            width: 100%;
            font-family: inherit;
            color: var(--t2-text);
            background: #fff;
        }
        .lead-capture-input:focus {
            outline: none;
            border-color: var(--t2-primary);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
        }
        .lead-capture-btn {
            height: 42px;
            border: none;
            border-radius: 10px;
            padding: 0 16px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            background: var(--t2-primary);
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .lead-capture-btn:hover { background: var(--t2-primary-dark); }
        .lead-capture-msg {
            margin-top: 6px;
            min-height: 18px;
            font-size: 0.78rem;
            color: var(--t2-text-muted);
        }
        .lead-capture-msg.success { color: #16a34a; }
        .lead-capture-msg.error { color: #dc2626; }
        .feedback-btn {
            background: #fff;
            border: 1px solid var(--t2-border);
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--t2-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            flex: 1;
            max-width: 180px;
            justify-content: center;
            font-family: inherit;
        }
        .feedback-btn:hover { border-color: var(--t2-primary); color: var(--t2-primary); }
        .feedback-btn.worked { border-color: #22c55e; color: #22c55e; }
        .feedback-btn.failed { border-color: #ef4444; color: #ef4444; }
        @keyframes t2PopupScale {
            from { transform: scale(0.95); opacity: 0.5; }
            to { transform: scale(1); opacity: 1; }
        }
        @keyframes t2PulseGlow {
            0%, 100% { box-shadow: 0 0 0 rgba(245, 158, 11, 0); transform: scale(1); }
            50% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.5); transform: scale(1.02); }
        }
        .go-store-attention { animation: t2PulseGlow 0.9s ease-in-out 3; }

        /* All codes modal */
        .all-codes-modal-content { max-width: 580px; }
        .all-codes-list { display: flex; flex-direction: column; gap: 12px; }
        .all-codes-item {
            border: 1px solid var(--t2-border);
            border-radius: 8px;
            overflow: hidden;
        }
        .all-codes-item .coupon-code-container { margin-bottom: 0; }
        .all-codes-item-desc { padding: 10px 16px; font-size: 0.85rem; color: var(--t2-text-muted); }

        .t2-mobile-shop { display: none; }
        @media (max-width: 768px) {
            .t2-store-banner { padding: 14px 16px; }
            .t2-banner-logo { width: 40px; height: 40px; }
            .t2-banner-name { font-size: 1rem; }
            .t2-main { padding: 20px 16px 40px; }
            .t2-hero-title { font-size: 1.5rem; }
            .coupon-row { flex-direction: column; align-items: flex-start; gap: 14px; }
            .btn-peel-sticker.btn-peel-sticker--wide,
            .btn-peel-sticker { width: 100%; justify-content: center; }
            .t2-mobile-shop { display: block; margin: 20px 0; text-align: center; }
            .t2-mobile-shop a {
                display: inline-block;
                padding: 12px 24px;
                background: var(--t2-primary);
                color: #fff;
                font-weight: 700;
                border-radius: 6px;
            }
            .t2-mobile-shop a:hover { background: var(--t2-primary-dark); color: #fff; }
        }
        @media (max-width: 480px) {
            .t2-quick-nav { flex-direction: column; }
            .t2-stats-bar { flex-direction: column; gap: 10px; }
            .filter-pill { font-size: 0.8rem; padding: 6px 12px; }
            .staff-pick-badge { display: none; }
        }
    </style>
    @include('partials.tracking-head')
</head>
<body class="landing-coupon">
@include('partials.tracking-body')
@include('partials.site-header')

<div class="t2-store-banner">
    <div class="t2-banner-brand">
        @if($campaign->brand)
            <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}" class="t2-banner-logo" loading="lazy">
        @elseif($campaign->logo)
            <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}" class="t2-banner-logo" loading="lazy">
        @else
            <img src="{{ asset('images/default-brand.svg') }}" alt="{{ $brandName }}" class="t2-banner-logo" loading="lazy">
        @endif
        <span class="t2-banner-name">{{ $brandName }}</span>
    </div>
    <a href="{{ route('click.redirect', ['slug' => $campaignSlug]) }}" class="t2-banner-cta" target="_blank" rel="noopener">Shop Now</a>
</div>

<div class="t2-main">
    <header class="t2-hero">
        <h1 class="t2-hero-title">
            {{ $brandName }} Coupons & Promo Codes – {{ now()->format('F Y') }}
        </h1>
        <p class="t2-hero-sub">
            Save up to <strong>{{ $maxPercent }}% off</strong> with verified discount codes &amp; exclusive deals.
        </p>
        <p class="t2-hero-disclosure">
            We may earn a commission when you shop through our links, at no extra cost to you.
        </p>
    </header>

    <nav class="t2-quick-nav" aria-label="Quick menu">
        <a href="#about-us">About us</a>
        <a href="#how-to-use">How to use a coupon code</a>
        <a href="#qa">Questions &amp; Answers</a>
        <a href="#policies">Policies &amp; notes</a>
    </nav>

    @php
        $workingCount = ($campaign->couponItems ?? collect())->count();
        $successRate = $workingCount > 0 ? min(99, 80 + (int)round($workingCount * 1.5)) : 89;
        $campaignSeed = (int) ($campaign->id ?? 0);
        $estimatedTotalSaved = min(99999, 7229 + $workingCount * 80 + ($campaignSeed % 5000));
    @endphp
    <div class="t2-stats-bar">
        <div class="t2-stats-item">
            <span class="t2-stats-value">{{ $workingCount }}</span>
            <span class="t2-stats-label">codes</span>
        </div>
        <div class="t2-stats-item">
            <span class="t2-stats-value">{{ $successRate }}%</span>
            <span class="t2-stats-label">success</span>
        </div>
        <div class="t2-stats-item">
            <span class="t2-stats-value">${{ number_format($estimatedTotalSaved, 0, '.', ',') }}</span>
            <span class="t2-stats-label">saved</span>
        </div>
    </div>

    <section class="t2-coupon-section">
        <div class="t2-coupon-header-title">
            Active {{ $brandName }} coupons
        </div>
        <div class="t2-coupon-header-meta">
            Last checked: 18 hours ago • Tracking coupons in the last 7 days.
        </div>
        <div class="t2-filter-tabs">
            <button class="filter-pill active" type="button" aria-pressed="true" aria-label="Show all deals">All Deals</button>
            <button class="filter-pill" type="button" aria-pressed="false" aria-label="Show coupon codes only">Codes</button>
            <button class="filter-pill" type="button" aria-pressed="false" aria-label="Show deals without codes">Deals</button>
            <button class="filter-pill" type="button" aria-pressed="false" aria-label="Show free shipping offers">Free Shipping</button>
            <button class="filter-pill" type="button" aria-pressed="false" aria-label="Show first order offers">First Order</button>
        </div>

        <div class="t2-coupon-list" id="coupon-list">
            @forelse($coupons as $coupon)
            @php
                $hasCode = !empty($coupon->code);
                $offerText = $ensureOfferUtf8($coupon->offer ?? '');
                $descriptionText = (string) ($coupon->description ?? '');
                $isFreeShipping = stripos($offerText, 'free shipping') !== false || stripos($descriptionText, 'free shipping') !== false;
                $tags = [];
                if ($isFreeShipping) { $tags[] = 'free-shipping'; }
                $tagAttr = implode(',', $tags);

                $offerValue = null;
                $offerType = 'percent';
                $currencySymbol = '';
                $currencyMap = [
                    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
                    'INR' => '₹', 'CAD' => 'C$', 'AUD' => 'A$',
                    'CHF' => 'CHF', 'CNY' => '¥',
                ];
                if ($offerText !== '') {
                    if (preg_match('/(\d+)\s*%/i', $offerText, $matches)) {
                        $offerValue = (int)$matches[1];
                        $offerType = 'percent';
                    } elseif (preg_match('/([€$£¥₹])\s*(\d+(?:[.,]\d+)?)/u', $offerText, $matches)) {
                        $currencySymbol = $matches[1];
                        $offerValue = $matches[2];
                        $offerType = 'currency';
                    } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*([€$£¥₹])/u', $offerText, $matches)) {
                        $currencySymbol = $matches[2];
                        $offerValue = $matches[1];
                        $offerType = 'currency';
                    } elseif (preg_match('/(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)\s*(\d+(?:[.,]\d+)?)/i', $offerText, $matches)) {
                        $currencyCode = strtoupper($matches[1]);
                        $currencySymbol = $currencyMap[$currencyCode] ?? $currencyCode;
                        $offerValue = $matches[2];
                        $offerType = 'currency';
                    } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)/i', $offerText, $matches)) {
                        $currencyCode = strtoupper($matches[2]);
                        $currencySymbol = $currencyMap[$currencyCode] ?? $currencyCode;
                        $offerValue = $matches[1];
                        $offerType = 'currency';
                    } elseif ($isFreeShipping) {
                        $offerValue = 'FREE';
                        $offerType = 'text';
                    }
                }
                if ($offerValue === null) {
                    $offerValue = 15;
                    $offerType = 'percent';
                }
                $hoursAgo = (($campaign->id ?? 0) * 7 + ($coupon->id ?? 0)) % 48 + 1;
                $uses = (($campaign->id ?? 0) * 11 + ($coupon->id ?? 0)) % 4900 + 100;
            @endphp
            <article
                class="coupon-row"
                data-type="{{ $hasCode ? 'code' : 'deal' }}"
                data-tags="{{ $tagAttr }}"
                data-coupon-id="{{ $coupon->id }}"
            >
                <div class="t2-coupon-content">
                    <div class="coupon-title">
                        @if($coupon->description)
                            {{ $coupon->description }}
                        @else
                            @if($offerType === 'currency')
                                Save {{ $currencySymbol }}{{ number_format((float)str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') }} on your order with this {{ $brandName }} promo code
                            @elseif($offerType === 'text' && $offerValue === 'FREE')
                                Get Free Shipping on your order with this {{ $brandName }} promo code
                            @else
                                Save {{ $offerValue }}% on your order with this {{ $brandName }} promo code
                            @endif
                        @endif
                    </div>
                    @if($loop->index < 2)
                        <div class="badge-row">
                            <div class="staff-pick-badge">STAFF PICK</div>
                            @if($loop->first)
                                <div class="top-pic-badge">TOP PICK</div>
                            @endif
                        </div>
                    @endif
                    <div class="coupon-meta-info">
                        <div class="meta-item">{{ $hoursAgo }} hours ago</div>
                        <div class="meta-item uses">{{ number_format($uses) }} Uses</div>
                    </div>
                    <div class="coupon-desc coupon-verified" aria-label="Coupon status">
                        <span class="coupon-verified-badge" aria-hidden="true">
                            <img src="{{ asset('images/verified-badge.png') }}" alt="">
                        </span>
                        <span class="coupon-verified-text">Verified recently</span>
                    </div>
                </div>
                @include('partials.coupon-get-code-btn', [
                    'hasCode' => $hasCode,
                    'code' => $coupon->code,
                    'couponId' => $coupon->id,
                    'url' => route('click.redirect', ['slug' => $campaignSlug]),
                ])
            </article>
            @empty
            <article class="section">
                <div class="section-title">No coupons available</div>
                <div class="section-body">
                    Please check back later. We are updating new deals.
                </div>
            </article>
            @endforelse

            @if($couponsWithCodes->isNotEmpty())
            <article class="coupon-row coupon-row-default"
                data-type="code"
                data-all-codes="1"
                data-coupon-id="all-codes"
            >
                <div class="t2-coupon-content">
                    <div class="coupon-title">
                        @if($defaultCouponOfferType === 'currency')
                            Save {{ $defaultCouponCurrencySymbol }}{{ number_format($defaultCouponOfferValue, 0, '.', '') }} on your order with this {{ $brandName }} promo code
                        @else
                            Save {{ $defaultCouponOfferValue }}% on your order with this {{ $brandName }} promo code
                        @endif
                    </div>
                    <div class="badge-row">
                        <div class="all-verified-codes-badge">All Verified Codes</div>
                    </div>
                    <div class="coupon-meta-info">
                        <div class="meta-item">18 hours ago</div>
                        <div class="meta-item uses">{{ number_format(($campaignSeed * 13 + 999) % 4900 + 100) }} Uses</div>
                    </div>
                </div>
                @include('partials.coupon-get-code-btn', [
                    'hasCode' => true,
                    'allCodes' => true,
                    'couponId' => 'all-codes',
                    'url' => route('click.redirect', ['slug' => $campaignSlug]),
                ])
            </article>
            @endif
        </div>

        <div class="t2-mobile-shop">
            <a href="{{ route('click.redirect', ['slug' => $campaignSlug]) }}" target="_blank" rel="nofollow sponsored noopener">Shop Now</a>
        </div>
    </section>

    <section class="section" id="about-us">
        <h2 class="section-title">About us</h2>
        <div class="section-body intro-content">
            @if($campaign->intro)
                @php
                    $intro = (string) $campaign->intro;
                    $hasHtml = $intro !== strip_tags($intro);
                @endphp
                @if($hasHtml)
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

    <section class="section qa-section" id="qa">
        <h2 class="section-title">Questions &amp; Answers</h2>
        <div class="qa-item">
            <button class="qa-question" onclick="toggleQA(this)">
                How do I use this coupon code?
                <span class="qa-icon">+</span>
            </button>
            <div class="qa-answer">
                Simply click "Get Deal", copy the coupon code, then apply it at checkout on the store's website.
            </div>
        </div>
        <div class="qa-item">
            <button class="qa-question" onclick="toggleQA(this)">
                Why doesn't my coupon work?
                <span class="qa-icon">+</span>
            </button>
            <div class="qa-answer">
                Some coupons require a minimum order value, specific products, or may have expired. Please double-check the terms before checkout.
            </div>
        </div>
        <div class="qa-item">
            <button class="qa-question" onclick="toggleQA(this)">
                Can I use more than one coupon?
                <span class="qa-icon">+</span>
            </button>
            <div class="qa-answer">
                Most stores allow only one coupon per order. Combining multiple offers is usually not supported.
            </div>
        </div>
        <div class="qa-item">
            <button class="qa-question" onclick="toggleQA(this)">
                Do you earn a commission from these deals?
                <span class="qa-icon">+</span>
            </button>
            <div class="qa-answer">
                Yes, we may earn a small commission when you make a purchase through our links, at no extra cost to you.
            </div>
        </div>
    </section>

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
</div>

@include('partials.site-footer')

<div id="couponModal" class="coupon-modal" role="dialog" aria-modal="true" aria-labelledby="coupon-modal-title">
    <div class="coupon-modal-stack">
        <div id="couponCopyToast" class="coupon-copy-toast" role="status" aria-live="polite" hidden>
            <div class="coupon-copy-toast-row">
                <span class="coupon-copy-toast-icon" aria-hidden="true">✓</span>
                <p class="coupon-copy-toast-text">Code copied! Go to <strong>{{ e($brandName) }}</strong></p>
                <button type="button" class="coupon-copy-toast-close" aria-label="Đóng" onclick="dismissCouponCopyToast()">✕</button>
            </div>
            <div class="coupon-copy-toast-progress" aria-hidden="true"></div>
        </div>
    <div class="coupon-modal-content">
        <button type="button" class="coupon-modal-close" aria-label="Close coupon popup" onclick="closeCouponPopup()">✕</button>
        <div class="t2-popup-banner">
            <div>
                @if($campaign->brand)
                    <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}" class="t2-popup-logo" loading="lazy">
                @elseif($campaign->logo)
                    <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}" class="t2-popup-logo" loading="lazy">
                @else
                    <img src="{{ asset('images/default-brand.svg') }}" alt="{{ $brandName }}" class="t2-popup-logo" loading="lazy">
                @endif
                <h3 class="t2-popup-title" id="coupon-modal-title">
                    {{ $brandName }} Promotion
                </h3>
                <p class="t2-popup-subtitle">
                    A Little Discount, A Lot of Joy—Save Now with {{ $brandName }} Promotion.
                </p>
            </div>
        </div>
        <div class="t2-popup-body">
            <div class="coupon-code-container">
                <div class="coupon-code-left">
                    <div class="coupon-code-icon">🏷</div>
                    <div id="modalCode" class="coupon-code-box">CODE123</div>
                </div>
                <button class="btn-copy-code-modal" id="copyCouponBtn" onclick="copyCoupon(this)" aria-label="Copy coupon code">COPY CODE</button>
            </div>
            <div class="popup-verification">
                <div class="verification-item">Recently checked and still active for most shoppers.</div>
                <div class="verification-item success">We can't guarantee every order, but many visitors report this code still works.</div>
            </div>
            <div class="coupon-modal-actions">
                <a href="#" target="_blank" rel="nofollow sponsored noopener"
                   class="coupon-btn store go-to-store-btn"
                   aria-label="Open store in new tab"
                   onclick="event.preventDefault(); const url = this.getAttribute('data-url'); if(url) { window.open(url, '_blank'); } return false;">
                    Go To The Store
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

<div id="allCodesModal" class="coupon-modal all-codes-modal" role="dialog" aria-modal="true" aria-labelledby="all-codes-modal-title">
    <div class="coupon-modal-content all-codes-modal-content">
        <button type="button" class="coupon-modal-close" aria-label="Close" onclick="closeAllCodesModal()">✕</button>
        <div class="t2-popup-banner">
            <div>
                @if($campaign->brand)
                    <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}" class="t2-popup-logo" loading="lazy">
                @elseif($campaign->logo)
                    <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}" class="t2-popup-logo" loading="lazy">
                @else
                    <img src="{{ asset('images/default-brand.svg') }}" alt="{{ $brandName }}" class="t2-popup-logo" loading="lazy">
                @endif
                <h3 class="t2-popup-title" id="all-codes-modal-title">All {{ $brandName }} Coupon Codes</h3>
                <p class="t2-popup-subtitle">Copy any code below and apply at checkout.</p>
            </div>
        </div>
        <div class="t2-popup-body">
            <div class="all-codes-list">
                @foreach($couponsWithCodes as $coupon)
                @php
                    $offerText = $ensureOfferUtf8($coupon->offer ?? '');
                    $offerValue = null;
                    $offerType = 'percent';
                    $currencySymbol = '';
                    $isFreeShipping = stripos($offerText, 'free shipping') !== false;
                    $currencyMap = [
                        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
                        'INR' => '₹', 'CAD' => 'C$', 'AUD' => 'A$',
                        'CHF' => 'CHF', 'CNY' => '¥',
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
                        } elseif (preg_match('/(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)\s*(\d+(?:[.,]\d+)?)/i', $offerText, $m)) {
                            $code = strtoupper($m[1]);
                            $currencySymbol = $currencyMap[$code] ?? $code;
                            $offerValue = $m[2];
                            $offerType = 'currency';
                        } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|JPY|INR|CAD|AUD|CHF|CNY)/i', $offerText, $m)) {
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
                        <button class="btn-copy-code-modal btn-copy-all-codes" type="button" data-code="{{ e($coupon->code) }}" aria-label="Copy code">COPY</button>
                    </div>
                    <div class="all-codes-item-desc">
                        @if($coupon->description)
                            {{ Str::limit($coupon->description, 80) }}
                        @elseif($offerType === 'currency' && $offerValue !== null)
                            Save {{ $currencySymbol }}{{ number_format((float)str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') }} on your order with this {{ $brandName }} promo code
                        @elseif($offerType === 'text' && $offerValue === 'FREE')
                            Get Free Shipping on your order with this {{ $brandName }} promo code
                        @elseif($offerValue !== null)
                            Save {{ $offerValue }}% on your order with this {{ $brandName }} promo code
                        @else
                            Save with this coupon code when you shop at {{ $brandName }} and apply it at checkout.
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="coupon-modal-actions" style="margin-top: 20px;">
                <a href="{{ route('click.redirect', ['slug' => $campaignSlug]) }}"
                   target="_blank" rel="nofollow sponsored noopener"
                   class="coupon-btn store">
                    Go To The Store
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
let couponCopyRedirectTimer = null;
let couponCopyToastHideTimer = null;
let currentCode = '';
let currentCouponRow = null;
let currentCouponId = null;
let currentAffUrl = null;
let affiliateAlreadyOpened = false;
function getStoredRevealed() { return {}; }
function saveRevealed(couponId, code) { return; }

function revealCodeInRow(couponId, code, affUrl) {
    const row = document.querySelector('.coupon-row[data-coupon-id="' + couponId + '"]');
    if (!row) return;
    const btn = row.querySelector('.btn-get-code');
    if (!btn) return;
    const codeSpan = document.createElement('span');
    codeSpan.className = 'coupon-revealed-code';
    codeSpan.textContent = code;
    codeSpan.title = 'Click to copy again';
    if (affUrl) codeSpan.dataset.url = affUrl;
    codeSpan.onclick = function(e) {
        e.stopPropagation();
        copyCodeToClipboard(code);
        const t = this.textContent;
        this.textContent = 'Copied ✓';
        this.style.background = '#f59e0b';
        this.style.color = '#fff';
        setTimeout(() => { this.textContent = t; this.style.background = ''; this.style.color = ''; }, 1500);
    };
    btn.parentNode.replaceChild(codeSpan, btn);
    saveRevealed(couponId, code);

    // Nếu popup all-codes đang mở, hiện luôn code thật cho item tương ứng
    document
        .querySelectorAll('#allCodesModal .all-codes-item .btn-copy-all-codes')
        .forEach(function (bt) {
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
    couponCopyToastHideTimer = setTimeout(function() {
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
    const activeTabBtn = document.querySelector('.filter-pill.active');
    const activeTab = activeTabBtn ? (activeTabBtn.dataset.tab || 'all') : 'all';
    if (type === 'deal' || activeTab === 'deals') {
        clearCouponModalHash();
        return;
    }
    if (!code) {
        clearCouponModalHash();
        return;
    }
    currentCouponRow = btn.closest('.coupon-row');
    openModalForCoupon(couponId, code, url);
}

function openModalForCoupon(couponId, code, affUrl) {
    currentCode = code;
    currentCouponId = couponId;
    currentAffUrl = affUrl || null;
    const codeBox = document.getElementById('modalCode');
    if (codeBox) {
        // Ẩn code cho tới khi user bấm Copy
        codeBox.innerText = '••••••••';
    }
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

function submitLeadEmail(btn){
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
            source_template: 'template2'
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

function handleCouponClick(btn){
    const actualBtn = btn.classList && btn.classList.contains('btn-get-code') ? btn : btn.querySelector('.btn-get-code');
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

        // Nếu đã mở affiliate trước đó (tab mới), chỉ mở modal để copy
        document.getElementById('allCodesModal').classList.add('active');
        resetLeadCapture('leadEmailInputAllCodes', 'leadCaptureMsgAllCodes', 'leadSubmitBtnAllCodes');
        setCouponModalHash('all-codes');
        document.querySelectorAll('.btn-copy-all-codes').forEach(function(bt) {
            bt.onclick = function() {
                const code = this.dataset.code;
                if (!code) return;
                copyCodeToClipboard(code);
                const originalText = this.textContent;

                // Hiện code thật trong list khi user copy
                const box = this
                    .closest('.all-codes-item')
                    ?.querySelector('.coupon-code-box');
                if (box && box.dataset.realCode) {
                    box.textContent = box.dataset.realCode;
                }

                this.textContent = 'Copied ✓';
                this.disabled = true;

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

    if (type === 'deal' || activeTab === 'deals') {
        if (url) window.open(url, '_blank');
        return false;
    }

    if (!code) {
        if (url) window.open(url, '_blank');
        return false;
    }

    currentCode = code;
    currentCouponId = couponId;
    currentCouponRow = actualBtn.closest('.coupon-row');

    // Flow mới cho Get Code:
    // - Nếu chưa mở affiliate tab nào: tab hiện tại -> aff, tab mới -> trang coupon + modal
    // - Nếu affiliate đã mở trước đó (tab mới): chỉ mở modal để copy
    if (!affiliateAlreadyOpened) {
        const couponUrl = new URL(window.location.href);
        couponUrl.searchParams.set('show_coupon', String(couponId));
        couponUrl.searchParams.set('code', String(code));
        couponUrl.searchParams.set('aff_opened', '1');
        couponUrl.hash = '';
        window.open(couponUrl.toString(), '_blank', 'noopener');
        if (url) window.location.href = url;
        return false;
    }

    openModalForCoupon(couponId, code, url);
    return false;
}

function closeCouponPopup(){
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
    clearCouponModalHash();
}
function closeAllCodesModal(){
    document.getElementById('allCodesModal').classList.remove('active');
    clearCouponModalHash();
}

function handleFeedback(btn, worked){
    const buttons = document.querySelectorAll('.feedback-btn');
    buttons.forEach(b => {
        b.disabled = true;
        b.style.opacity = '0.6';
        b.style.cursor = 'not-allowed';
    });
    if (worked) {
        btn.style.background = '#dcfce7';
        btn.style.borderColor = '#22c55e';
        btn.innerHTML = '<span class="feedback-icon">✓</span><span>Thank you!</span>';
    } else {
        btn.style.background = '#fef2f2';
        btn.style.borderColor = '#ef4444';
        btn.innerHTML = '<span class="feedback-icon">✓</span><span>Thank you!</span>';
    }
}

function copyCoupon(btn){
    if(!currentCode) return;

    if (!btn.dataset.copyLabelDefault) {
        btn.dataset.copyLabelDefault = btn.innerText.trim();
    }

    copyCodeToClipboard(currentCode);
    const originalText = btn.dataset.copyLabelDefault;

    const codeBox = document.getElementById('modalCode');
    if (codeBox) {
        codeBox.innerText = currentCode;
    }

    btn.innerText = 'Copied ✓';
    btn.disabled = true;
    showCouponCopyToast();

    const goBtn = document.querySelector('.go-to-store-btn');
    if(goBtn){
        goBtn.classList.remove('go-store-attention');
        void goBtn.offsetWidth;
        goBtn.classList.add('go-store-attention');
    }

    const affUrl = currentAffUrl || (goBtn ? goBtn.getAttribute('data-url') : null);
    if (currentCouponId && currentCode) {
        revealCodeInRow(currentCouponId, currentCode, affUrl);
    }

    if (couponCopyRedirectTimer) clearTimeout(couponCopyRedirectTimer);
    couponCopyRedirectTimer = setTimeout(function() {
        couponCopyRedirectTimer = null;
        hideCouponCopyToast();
        if (affUrl && !affiliateAlreadyOpened) {
            window.open(affUrl, '_blank');
        }
        btn.innerText = originalText;
        btn.disabled = false;
    }, 2300);
}
function toggleQA(el){
    const item = el.closest('.qa-item');
    item.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function () {
    const affUrl = (function() {
        const b = document.querySelector('.btn-get-code[data-url]');
        return b ? b.dataset.url : null;
    })();
    // Không khôi phục code đã reveal từ lần tải trước.

    // Ẩn toàn bộ mã trong popup all-codes cho tới khi user copy
    document
        .querySelectorAll('#allCodesModal .all-codes-item .coupon-code-box')
        .forEach(function (box) {
            const real = (box.textContent || '').trim();
            box.dataset.realCode = real;
            box.textContent = '••••••••';
        });

    const urlParams = new URLSearchParams(window.location.search);
    affiliateAlreadyOpened = urlParams.get('aff_opened') === '1';
    const showCouponId = urlParams.get('show_coupon');
    const codeFromUrl = urlParams.get('code');
    if (showCouponId && codeFromUrl) {
        try {
            const decoded = decodeURIComponent(codeFromUrl);
            currentCode = decoded;
            currentCouponId = showCouponId;
            currentCouponRow = document.querySelector('.coupon-row[data-coupon-id="' + showCouponId + '"]');
            revealCodeInRow(showCouponId, decoded, affUrl);
            openModalForCoupon(showCouponId, decoded, affUrl);
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

                row.style.display = show ? 'flex' : 'none';
            });
        });
    });
});

@if(isset($pageView) && $pageView)
(function() {
    const pageViewId = {{ $pageView->id }};
    const startTime = Date.now();
    let timeOnPage = 0;
    let isBounce = true;
    let hasInteracted = false;
    
    ['click', 'scroll', 'keydown', 'touchstart'].forEach(event => {
        document.addEventListener(event, function() {
            hasInteracted = true;
            isBounce = false;
        }, { once: true });
    });
    
    setInterval(function() {
        timeOnPage = Math.floor((Date.now() - startTime) / 1000);
    }, 1000);
    
    window.addEventListener('beforeunload', function() {
        timeOnPage = Math.floor((Date.now() - startTime) / 1000);
        const data = JSON.stringify({
            time_on_page: timeOnPage,
            is_bounce: isBounce && !hasInteracted && timeOnPage < 30
        });
        navigator.sendBeacon(
            '{{ route("analytics.update-page-view", ":id") }}'.replace(':id', pageViewId),
            data
        );
    });
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            timeOnPage = Math.floor((Date.now() - startTime) / 1000);
            fetch('{{ route("analytics.update-page-view", ":id") }}'.replace(':id', pageViewId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    time_on_page: timeOnPage,
                    is_bounce: isBounce && !hasInteracted && timeOnPage < 30
                })
            }).catch(() => {});
        }
    });
})();
@endif
</script>

@include('partials.back-to-top')

</body>
</html>
