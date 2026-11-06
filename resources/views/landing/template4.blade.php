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

        // Thứ tự theo sort_order đã áp dụng trong Campaign::couponItems()
        $coupons = ($campaign->couponItems ?? collect())->values();
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
        $couponsDeals = $coupons->filter(fn ($c) => empty($c->code))->values();
        $codeCount = $couponsWithCodes->count();
        $dealCount = $couponsDeals->count();
        $allCount = $coupons->count();
        $verifiedCount = $codeCount;
        $bestOfferLabel = $maxPercent > 0 ? ($maxPercent . '% OFF') : '—';
        $voteCount = 80 + (((int) ($campaign->id ?? 1) * 37) % 220);
        $ratingValue = '5.0';
        
        $campaignSlug = $campaign->slug;

        $brandName = $campaign->brand->name ?? $campaign->title;
        $heroSub = trim(strip_tags((string) ($campaign->subtitle ?? '')));
        if ($heroSub === '') {
            $heroSub = 'Great chance to save money at ' . $brandName . ' because sale season is here. Time to go shopping!';
        }
        $rawIntro = (string) ($campaign->subtitle ?? $campaign->intro ?? '');
        $metaTitle = $brandName . ' . Coupons and Promo Codes';
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
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
            --primary: #16a34a;
            --primary-dark: #15803d;
            --primary-light: #bbf7d0;
            --t3-primary: #16a34a;
            --t3-primary-dark: #15803d;
            --t3-primary-soft: #f0fdf4;
            --t3-accent: #16a34a;
            --t3-accent-hover: #15803d;
            --t3-hero-bg: linear-gradient(135deg, #22c55e 0%, #16a34a 55%, #14532d 100%);
            --t3-text: #1f2937;
            --t3-text-muted: #6b7280;
            --t3-bg: #f3f4f6;
            --t3-card: #ffffff;
            --t3-border: #e5e7eb;
            --t3-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            --t3-ribbon: linear-gradient(180deg, #15803d 0%, #16a34a 50%, #22c55e 100%);
        }
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 15px;
            line-height: 1.6;
            color: var(--t3-text);
            background: var(--t3-bg);
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; transition: color 0.2s ease; }

        /* Store strip — kiểu banner coupon site lớn */
        .t3-store-banner {
            background: var(--t3-card);
            border-bottom: 1px solid var(--t3-border);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 1px 0 rgba(255,255,255,0.8) inset;
        }
        .t3-banner-brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .t3-banner-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            background: #fff;
            border-radius: 12px;
            padding: 8px;
            border: 1px solid var(--t3-border);
            box-shadow: var(--t3-shadow);
        }
        .t3-banner-name {
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.02em;
            color: var(--t3-text);
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
        }
        .t3-banner-tagline {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--t3-primary);
            margin-top: 2px;
        }
        .t3-banner-cta {
            display: inline-flex;
            align-items: center;
            padding: 12px 28px;
            background: var(--t3-accent);
            color: #fff;
            font-weight: 800;
            font-size: 0.9rem;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(234, 88, 12, 0.35);
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
        }
        .t3-banner-cta:hover {
            background: var(--t3-accent-hover);
            transform: translateY(-1px);
            color: #fff;
            box-shadow: 0 6px 20px rgba(234, 88, 12, 0.4);
        }

        .t3-main {
            max-width: 1140px;
            margin: 0 auto;
            padding: 32px 24px 56px;
        }

        .t3-hero {
            margin-bottom: 28px;
            padding: 28px 32px;
            background: var(--t3-card);
            border-radius: 20px;
            border: 1px solid var(--t3-border);
            box-shadow: var(--t3-shadow);
            position: relative;
            overflow: hidden;
        }
        .t3-hero::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: min(45%, 380px);
            height: 100%;
            background: radial-gradient(ellipse at 100% 0%, rgba(5, 150, 105, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .t3-hero-title {
            position: relative;
            font-size: clamp(1.45rem, 3.5vw, 2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 10px;
            color: var(--t3-text);
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
            line-height: 1.2;
            max-width: 48rem;
        }
        .t3-hero-sub {
            position: relative;
            font-size: 1rem;
            color: var(--t3-text-muted);
        }
        .t3-hero-sub strong { color: var(--t3-primary); font-weight: 800; }
        .t3-hero-disclosure {
            position: relative;
            margin-top: 12px;
            font-size: 0.8rem;
            color: #94a3b8;
            padding-top: 12px;
            border-top: 1px solid var(--t3-border);
        }

        .t3-quick-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
            margin-bottom: 24px;
        }
        .t3-quick-nav a {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--t3-text-muted);
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--t3-card);
            border: 1px solid var(--t3-border);
            transition: all 0.2s ease;
        }
        .t3-quick-nav a:hover {
            color: var(--t3-primary-dark);
            border-color: var(--t3-primary);
            background: var(--t3-primary-soft);
        }

        .t3-stats-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .t3-stats-item {
            display: inline-flex;
            align-items: baseline;
            gap: 8px;
            padding: 12px 20px;
            background: var(--t3-card);
            border: 1px solid var(--t3-border);
            border-radius: 14px;
            box-shadow: var(--t3-shadow);
        }
        .t3-stats-value {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--t3-text);
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
        }
        .t3-stats-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--t3-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .t3-coupon-header-title {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 6px;
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
            letter-spacing: -0.02em;
        }
        .t3-coupon-header-meta {
            font-size: 1rem;
            color: var(--t3-text-muted);
            margin-bottom: 16px;
        }

        .t3-filter-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 22px; }
        .filter-pill {
            border-radius: 999px;
            padding: 9px 18px;
            font-size: 0.82rem;
            border: 1px solid var(--t3-border);
            background: var(--t3-card);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            font-family: inherit;
            color: var(--t3-text-muted);
        }
        .filter-pill:hover {
            border-color: var(--t3-primary);
            color: var(--t3-primary-dark);
            background: var(--t3-primary-soft);
        }
        .filter-pill.active {
            background: var(--t3-text);
            border-color: var(--t3-text);
            color: #fff;
            font-weight: 700;
        }

        .t3-coupon-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .coupon-row {
            display: grid;
            grid-template-columns: 108px 1fr minmax(130px, 150px);
            align-items: stretch;
            min-height: 140px;
            background: var(--t3-card);
            border: 1px solid var(--t3-border);
            border-radius: 16px;
            box-shadow: var(--t3-shadow);
            transition: box-shadow 0.25s ease, border-color 0.2s, transform 0.2s;
            overflow: hidden;
        }
        .coupon-row:hover {
            box-shadow: 0 12px 40px -12px rgba(15, 23, 42, 0.15);
            border-color: rgba(5, 150, 105, 0.35);
            transform: translateY(-2px);
        }
        .t3-offer-strip {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 14px 10px;
            background: var(--t3-ribbon);
            color: #fff;
            text-align: center;
            gap: 2px;
            min-height: 100%;
            position: relative;
        }
        .t3-offer-strip::after {
            content: '';
            position: absolute;
            right: 0;
            top: 10%;
            bottom: 10%;
            width: 1px;
            background: linear-gradient(180deg, transparent, rgba(255,255,255,0.45), transparent);
        }
        .t3-offer-strip--deal {
            background: linear-gradient(180deg, var(--t3-primary-dark) 0%, var(--t3-primary) 100%);
        }
        .t3-offer-num {
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
            font-weight: 800;
            font-size: 1.65rem;
            line-height: 1;
            letter-spacing: -0.03em;
        }
        .t3-offer-unit {
            font-size: 1rem;
            font-weight: 800;
            opacity: 0.95;
        }
        .t3-offer-caption {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            opacity: 0.92;
        }
        .t3-coupon-middle {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 18px 20px 18px 18px;
            min-width: 0px;
        }
        .t3-coupon-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px 14px;
            background: linear-gradient(90deg, #f8fafc 0%, #fff 100%);
            border-left: 1px dashed var(--t3-border);
        }
        .t3-coupon-content { flex: 1; min-width: 0; }
        .coupon-title {
            font-weight: 600;
            font-size: 1rem;
            line-height: 1.4;
            color: var(--t3-text);
            margin-bottom: 6px;
        }
        .staff-pick-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--t3-primary-soft);
            color: var(--t3-primary-dark);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            margin-bottom: 6px;
            border: 1px solid rgba(5, 150, 105, 0.25);
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
            background: var(--t3-text);
            color: #fff;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .top-pic-badge::before { content: '🔥'; font-size: 0.6rem; }
        .all-verified-codes-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--t3-primary);
            color: #fff;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .all-verified-codes-badge::before { content: '✓'; font-size: 0.7rem; font-weight: 900; }
        .coupon-meta-info {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 0.8rem;
            color: var(--t3-text-muted);
        }
        .meta-item { display: flex; align-items: center; gap: 5px; }
        .meta-item::before { content: '✓'; color: var(--t3-primary); font-weight: 800; }
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
            justify-content: center;
            padding: 0px 16px;
            font-size: 0.9rem;
            font-weight: 700;
            font-family: ui-monospace, monospace;
            letter-spacing: 0.05em;
            background: var(--t3-primary-soft);
            color: var(--t3-primary-dark);
            border: 1px dashed var(--t3-primary);
            border-radius: 10px;
            white-space: nowrap;
            cursor: pointer;
            flex-shrink: 0;
            width: 100%;
            box-sizing: border-box;
        }

        .section {
            background: var(--t3-card);
            border: 1px solid var(--t3-border);
            padding: 28px 32px;
            margin-bottom: 20px;
            border-radius: 18px;
            box-shadow: var(--t3-shadow);
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--t3-text);
            font-family: 'Plus Jakarta Sans', 'DM Sans', sans-serif;
            letter-spacing: -0.02em;
        }
        .section-body {
            font-size: 0.85rem;
            color: var(--t3-text-muted);
            line-height: 1.75;
        }
        .section-body ul { padding-left: 18px; margin: 6px 0; }
        .intro-content { line-height: 1.75; color: var(--t3-text-muted); }
        .intro-content p { margin-bottom: 1rem; color: var(--t3-text); }
        .intro-content p:last-child { margin-bottom: 0; }
        .intro-content a { color: var(--t3-primary); text-decoration: underline; }
        .intro-content a:hover { color: var(--t3-primary-dark); }

        /* Q&A */
        .qa-item {
            border: 1px solid var(--t3-border);
            margin-bottom: 8px;
            overflow: hidden;
            background: var(--t3-card);
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
            color: var(--t3-text);
            font-family: inherit;
        }
        .qa-icon { font-size: 1.2rem; transition: transform 0.3s ease; }
        .qa-answer {
            max-height: 0;
            overflow: hidden;
            padding: 0 16px;
            font-size: 0.85rem;
            color: var(--t3-text-muted);
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
            background: var(--t3-card);
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
        .t3-popup-banner {
            background: var(--t3-hero-bg);
            padding: 28px 24px 32px;
            text-align: center;
            position: relative;
        }
        .t3-popup-logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #fff;
            padding: 10px;
            margin: 0 auto 12px;
            object-fit: contain;
        }
        .t3-popup-title { font-size: 1.2rem; font-weight: 700; color: #fff; margin: 0; }
        .t3-popup-subtitle { font-size: 0.9rem; color: rgba(255,255,255,0.9); margin-top: 8px; }
        .t3-popup-banner { flex-shrink: 0; }
        .t3-popup-body {
            padding: 24px;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
        .coupon-code-container {
            background: var(--t3-primary-dark);
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
            background: var(--t3-primary);
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
            background: var(--t3-primary);
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
            background: var(--t3-primary-dark);
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
            color: var(--t3-text-muted);
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--t3-border);
            flex-wrap: wrap;
            gap: 8px;
        }
        .verification-item { display: flex; align-items: center; gap: 6px; }
        .verification-item::before { content: '✓'; color: var(--t3-primary); font-weight: 700; }
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
            background: var(--t3-primary);
            color: #fff;
        }
        .coupon-btn.store:hover {
            background: var(--t3-primary-dark);
            transform: translateY(-1px);
            color: #fff;
        }
        .popup-feedback { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--t3-border); }
        .popup-feedback-question { text-align: center; font-size: 0.95rem; font-weight: 600; margin-bottom: 12px; }
        .popup-feedback-buttons { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .lead-capture {
            margin-top: 14px;
        }
        .lead-capture-label {
            margin: 0 0 8px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--t3-text);
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
            border: 1px solid var(--t3-border);
            padding: 0 12px;
            font-size: 0.9rem;
            width: 100%;
            font-family: inherit;
            color: var(--t3-text);
            background: #fff;
        }
        .lead-capture-input:focus {
            outline: none;
            border-color: var(--t3-primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.18);
        }
        .lead-capture-btn {
            height: 42px;
            border: none;
            border-radius: 10px;
            padding: 0 16px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #fff;
            background: var(--t3-primary);
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .lead-capture-btn:hover { background: var(--t3-primary-dark); }
        .lead-capture-msg {
            margin-top: 6px;
            min-height: 18px;
            font-size: 0.78rem;
            color: var(--t3-text-muted);
        }
        .lead-capture-msg.success { color: #16a34a; }
        .lead-capture-msg.error { color: #dc2626; }
        .feedback-btn {
            background: #fff;
            border: 1px solid var(--t3-border);
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--t3-text);
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
        .feedback-btn:hover { border-color: var(--t3-primary); color: var(--t3-primary); }
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
            border: 1px solid var(--t3-border);
            border-radius: 8px;
            overflow: hidden;
        }
        .all-codes-item .coupon-code-container { margin-bottom: 0; }
        .all-codes-item-desc { padding: 10px 16px; font-size: 0.85rem; color: var(--t3-text-muted); }

        .t3-mobile-shop { display: none; }
        @media (max-width: 900px) {
            .t3-coupon-list { grid-template-columns: 1fr; }
            .coupon-row {
                grid-template-columns: 1fr;
            }
            .t3-offer-strip {
                flex-direction: row;
                justify-content: center;
                gap: 8px;
                padding: 12px 16px;
                border-radius: 0;
            }
            .t3-offer-strip::after { display: none; }
            .t3-offer-num { font-size: 1.4rem; }
            .t3-coupon-cta {
                border-left: none;
                border-top: 1px dashed var(--t3-border);
                padding: 14px 16px;
            }
            .btn-peel-sticker { max-width: 280px; margin: 0 auto; }
        }
        @media (max-width: 768px) {
            .t3-store-banner { padding: 14px 18px; }
            .t3-banner-logo { width: 48px; height: 48px; }
            .t3-banner-name { font-size: 1.15rem; }
            .t3-main { padding: 22px 16px 44px; }
            .t3-hero { padding: 22px 20px; border-radius: 16px; }
            .t3-mobile-shop { display: block; margin: 24px 0; text-align: center; }
            .t3-mobile-shop a {
                display: inline-block;
                padding: 14px 28px;
                background: var(--t3-accent);
                color: #fff;
                font-weight: 800;
                border-radius: 999px;
                box-shadow: 0 4px 14px rgba(234, 88, 12, 0.35);
            }
            .t3-mobile-shop a:hover { background: var(--t3-accent-hover); color: #fff; }
        }
        @media (max-width: 567px) {
            .coupon-row {
                border-radius: 14px;
            }
            .t3-offer-strip {
                border-radius: 14px 14px 0 0;
            }
        }
        @media (max-width: 480px) {
            .t3-stats-bar { flex-direction: column; }
            .t3-stats-item { width: 100%; justify-content: space-between; }
            .filter-pill { font-size: 0.78rem; padding: 8px 14px; }
            .staff-pick-badge { display: none; }
        }

        body.landing-coupon.t4-page {
            background: #f4f5f7;
            color: #374151;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        .t4-wrap {
            max-width: 1120px;
            margin: 0 auto;
            padding: 24px 16px 56px;
            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            gap: 28px;
            align-items: start;
        }
        .t4-sidebar { position: sticky; top: 16px; }
        .t4-store-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 22px 18px 18px;
            text-align: center;
        }
        .t4-logo-wrap {
            width: 118px;
            height: 118px;
            margin: 0 auto 12px;
            border-radius: 50%;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .t4-logo-wrap img {
            width: 78px;
            height: 78px;
            object-fit: contain;
        }
        .t4-store-name {
            font-size: 1.05rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }
        .t4-stars { color: #f59e0b; letter-spacing: 1px; font-size: 0.95rem; }
        .t4-votes { color: #6b7280; font-size: 0.82rem; margin-top: 2px; }
        .t4-rate {
            display: inline-block;
            margin-top: 4px;
            color: #16a34a;
            font-size: 0.82rem;
            text-decoration: none;
        }
        .t4-rate:hover { text-decoration: underline; color: #15803d; }
        .t4-alert-btn {
            display: block;
            width: 100%;
            margin-top: 16px;
            padding: 9px 12px;
            border: 2px solid #16a34a;
            background: #fff;
            color: #16a34a;
            font-weight: 700;
            font-size: 0.92rem;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
        }
        .t4-alert-btn:hover { background: #f0fdf4; }
        .t4-alert-form { display: none; margin-top: 10px; text-align: left; }
        .t4-alert-form.is-open { display: block; }
        .t4-stats-card {
            margin-top: 14px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            font-size: 0.86rem;
        }
        .t4-stats-head {
            padding: 10px 14px;
            font-weight: 700;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
        }
        .t4-stats-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 14px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
        }
        .t4-stats-row:last-child { border-bottom: none; }
        .t4-stats-row strong { color: #111827; font-weight: 700; }
        .t4-hero h1 {
            font-size: 1.7rem;
            line-height: 1.25;
            color: #111827;
            font-weight: 700;
            margin: 4px 0 10px;
        }
        .t4-hero p { color: #6b7280; font-size: 0.95rem; margin-bottom: 16px; }
        .t4-hero p strong { color: #16a34a; font-weight: 700; }
        .t4-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .t4-page .filter-pill {
            border-radius: 4px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            padding: 7px 14px;
            font-weight: 600;
            font-size: 0.88rem;
        }
        .t4-page .filter-pill:hover { border-color: #16a34a; color: #16a34a; }
        .t4-page .filter-pill.active {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
        .t4-coupon-list { display: flex; flex-direction: column; gap: 14px; }
        .t4-page .coupon-row {
            display: grid;
            grid-template-columns: 150px minmax(0, 1fr) 168px;
            align-items: center;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: visible;
            box-shadow: none;
            min-height: 0;
        }
        .t4-page .coupon-row:hover {
            transform: none;
            box-shadow: none;
            border-color: #e5e7eb;
        }
        .t4-offer {
            display: flex;
            align-items: center;
            justify-content: center;
            align-self: stretch;
            padding: 18px 10px;
            color: #16a34a;
            font-weight: 800;
            font-size: 1.55rem;
            letter-spacing: -0.02em;
            border-right: 1px solid #f3f4f6;
            text-align: center;
            line-height: 1.15;
        }
        .t4-coupon-body { padding: 16px 12px 16px 18px; min-width: 0; }
        .t4-verified {
            color: #16a34a;
            font-weight: 700;
            font-size: 0.92rem;
            margin-bottom: 6px;
        }
        .t4-coupon-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 6px;
        }
        .t4-coupon-desc {
            color: #6b7280;
            font-size: 0.88rem;
            line-height: 1.45;
            margin-bottom: 0;
        }
        .t4-coupon-cta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 16px 16px 16px 0;
        }
        .t4-page .btn-peel-sticker {
            width: 148px;
            max-width: 148px;
            flex: 0 0 148px;
        }
        .t4-page .btn-peel-sticker .peel-inner {
            min-width: 148px;
            width: 148px;
            height: 40px;
        }
        .t4-page .btn-peel-sticker .peel-sheet {
            width: calc(100% - 15px);
            opacity: 1;
        }
        .t4-page .qa-item.active .qa-answer { max-height: 480px; padding: 0 0 14px; }
        .t4-page .qa-answer { padding: 0; }
        .t4-section {
            margin-top: 28px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px 22px 22px;
        }
        .t4-section h2 {
            font-size: 1.2rem;
            color: #111827;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .t4-section .intro-content,
        .t4-section .intro-content p {
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.7;
        }
        .t4-section .intro-content p + p { margin-top: 12px; }
        .t4-section .intro-content strong { color: #111827; }
        .t4-howto ol { padding-left: 18px; color: #4b5563; font-size: 0.95rem; line-height: 1.65; }
        .t4-howto li { margin-bottom: 8px; }
        .t4-howto li::marker { font-weight: 700; }
        .t4-page .qa-item { border: none; border-bottom: 1px solid #e5e7eb; border-radius: 0; margin-bottom: 0; }
        .t4-page .qa-question {
            padding: 12px 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
        }
        .t4-page .qa-answer { color: #4b5563; font-size: 0.9rem; }
        .t4-empty { background: #fff; border: 1px dashed #d1d5db; padding: 24px; text-align: center; color: #6b7280; border-radius: 6px; }
        @media (max-width: 900px) {
            .t4-wrap { grid-template-columns: 1fr; }
            .t4-sidebar { position: static; max-width: 360px; }
            .t4-page .coupon-row { grid-template-columns: 1fr; }
            .t4-offer { border-right: none; border-bottom: 1px solid #f3f4f6; padding: 12px; font-size: 1.3rem; }
            .t4-coupon-cta { justify-content: center; padding: 0 16px 16px; }
        }
    </style>
    @include('partials.tracking-head')
</head>
<body class="landing-coupon t4-page">
@include('partials.tracking-body')
@include('partials.site-header')

@php
    $workingCount = $allCount;
    $campaignSeed = (int) ($campaign->id ?? 0);
@endphp

<div class="t4-wrap">
    <aside class="t4-sidebar">
        <div class="t4-store-card">
            <div class="t4-logo-wrap">
                @if($campaign->brand)
                    <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}" loading="lazy" onerror="this.onerror=null;this.src='{{ asset('images/default-brand.svg') }}';">
                @elseif($campaign->logo)
                    <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}" loading="lazy">
                @else
                    <img src="{{ asset('images/default-brand.svg') }}" alt="{{ $brandName }}" loading="lazy">
                @endif
            </div>
            <div class="t4-store-name">{{ $brandName }}</div>
            <div class="t4-stars" aria-label="{{ $ratingValue }} out of 5 stars">★★★★★</div>
            <div class="t4-votes">{{ $ratingValue }} / {{ $voteCount }} votes</div>
            <a class="t4-rate" href="#qa">Rate it</a>
            <button type="button" class="t4-alert-btn" onclick="document.getElementById('t4-alert-form').classList.toggle('is-open')">Get Coupon Alert</button>
            <div id="t4-alert-form" class="t4-alert-form lead-capture">
                <p class="lead-capture-label">Get exclusive discount codes.</p>
                <div class="lead-capture-row">
                    <input id="leadEmailInputSidebar" type="email" class="lead-capture-input" placeholder="Enter your email">
                    <button id="leadSubmitBtnSidebar" type="button" class="lead-capture-btn"
                            data-input-id="leadEmailInputSidebar" data-msg-id="leadCaptureMsgSidebar"
                            onclick="submitLeadEmail(this)">Send</button>
                </div>
                <p id="leadCaptureMsgSidebar" class="lead-capture-msg"></p>
            </div>
        </div>
        <div class="t4-stats-card">
            <div class="t4-stats-head">{{ $allCount }} Coupons, {{ $verifiedCount }} Verified Coupons</div>
            <div class="t4-stats-row"><span>Coupon Codes</span><strong>{{ $codeCount }}</strong></div>
            <div class="t4-stats-row"><span>Deals</span><strong>{{ $dealCount }}</strong></div>
            <div class="t4-stats-row"><span>Best Offer</span><strong>{{ $bestOfferLabel }}</strong></div>
        </div>
    </aside>

    <div class="t4-main">
        <header class="t4-hero">
            <h1>{{ $brandName }} . Coupons and Promo Codes</h1>
            <p>{!! preg_replace('/\b' . preg_quote($brandName, '/') . '\b/u', '<strong>' . e($brandName) . '</strong>', e($heroSub), 1) !!}</p>
        </header>

        <div class="t4-filters">
            <button class="filter-pill" type="button" data-tab="all" aria-pressed="false">All({{ $allCount }})</button>
            <button class="filter-pill active" type="button" data-tab="verified" aria-pressed="true">Verified({{ $verifiedCount }})</button>
            <button class="filter-pill" type="button" data-tab="codes" aria-pressed="false">Codes({{ $codeCount }})</button>
            <button class="filter-pill" type="button" data-tab="deals" aria-pressed="false">Deals({{ $dealCount }})</button>
        </div>

        <div class="t4-coupon-list" id="coupon-list">
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

                if ($offerType === 'percent') {
                    $offerLabel = $offerValue . '% OFF';
                } elseif ($offerType === 'currency') {
                    $offerLabel = $currencySymbol . number_format((float) str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') . ' OFF';
                } else {
                    $offerLabel = 'FREE SHIP';
                }

                $generatedBlurb = 'A popular pick for ' . $brandName . ' fans who want great value without paying full price – Click "Get Code" to get the code.';
                if ($descriptionText !== '') {
                    $couponTitle = trim((string) \Illuminate\Support\Str::before($descriptionText, '.')) ?: $descriptionText;
                    $rest = trim((string) \Illuminate\Support\Str::after($descriptionText, '.'));
                    $couponBlurb = ($rest !== '' && strcasecmp($rest, $couponTitle) !== 0) ? $descriptionText : $generatedBlurb;
                } else {
                    $couponTitle = $offerType === 'currency'
                        ? 'Save ' . $currencySymbol . number_format((float) str_replace([',', ' '], ['', ''], $offerValue), 0, '.', '') . ' on your order'
                        : ($offerType === 'text' ? 'Get Free Shipping on your order' : 'Get ' . $offerValue . '% OFF your order');
                    $couponBlurb = $generatedBlurb;
                }
            @endphp
            <article
                class="coupon-row"
                data-type="{{ $hasCode ? 'code' : 'deal' }}"
                data-tags="{{ $tagAttr }}"
                data-coupon-id="{{ $coupon->id }}"
            >
                <div class="t4-offer">{{ $offerLabel }}</div>
                <div class="t4-coupon-body">
                    <div class="t4-verified">{{ $hasCode ? 'Verified Code' : 'Verified Deal' }}</div>
                    <div class="t4-coupon-title">{{ $couponTitle }}</div>
                    <div class="t4-coupon-desc">{{ $couponBlurb }}</div>
                </div>
                <div class="t4-coupon-cta">
                    @include('partials.coupon-get-code-btn', [
                        'hasCode' => $hasCode,
                        'code' => $coupon->code,
                        'couponId' => $coupon->id,
                        'url' => route('click.redirect', ['slug' => $campaignSlug]),
                    ])
                </div>
            </article>
            @empty
            <div class="t4-empty">No coupons available. Please check back later.</div>
            @endforelse
        </div>

        <section class="t4-section" id="about-us">
            <h2>About store</h2>
            <div class="intro-content">
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
                        At <strong>{{ $brandName }}</strong>, our mission is to improve the quality of life for people everywhere through innovative ideas in everyday products. We carefully curate the latest discounts, special offers, and limited-time promotions so you can enjoy the best value on your favorite products.
                    </p>
                    <p>
                        We believe that everyone deserves to feel their best, and that's why we're committed to delivering the highest quality products that promote relaxation and comfort. Shop smarter and spend less with verified {{ $brandName }} coupon codes on this page.
                    </p>
                @endif
            </div>
        </section>

        <section class="t4-section t4-howto" id="how-to-use">
            <h2>How to apply {{ $brandName }} . coupon codes</h2>
            <ol>
                <li>Find your {{ $brandName }} . coupons and discount codes on this page or {{ config('app.name') }}, click <strong>GET CODE</strong>, then copy the code to your clipboard.</li>
                <li>Go to {{ $brandName }} , add items to your cart, and proceed to checkout.</li>
                <li>Paste your {{ $brandName }} . coupon in the Promo Code or Discount Code field and click <strong>Apply</strong> to see your savings.</li>
            </ol>
        </section>

        <section class="t4-section qa-section" id="qa">
            <h2>{{ $brandName }} . Questions &amp; Answers</h2>
            <div class="qa-item">
                <button class="qa-question" type="button" onclick="toggleQA(this)">
                    Q: Why should I visit {{ config('app.name') }} for {{ $brandName }} . coupons?
                    <span class="qa-icon">+</span>
                </button>
                <div class="qa-answer">A: {{ config('app.name') }} collects the top discounts from {{ $brandName }} , even at the last minute, and updates continually to help you save.</div>
            </div>
            <div class="qa-item">
                <button class="qa-question" type="button" onclick="toggleQA(this)">
                    Q: Where to find {{ $brandName }} . promo codes?
                    <span class="qa-icon">+</span>
                </button>
                <div class="qa-answer">A: On the official {{ $brandName }} website or on {{ config('app.name') }} for more verified options.</div>
            </div>
            <div class="qa-item">
                <button class="qa-question" type="button" onclick="toggleQA(this)">
                    Q: Will all {{ $brandName }} . discounts automatically be applied at checkout?
                    <span class="qa-icon">+</span>
                </button>
                <div class="qa-answer">A: No. Some deals require a code at checkout; others apply automatically depending on the offer.</div>
            </div>
            <div class="qa-item">
                <button class="qa-question" type="button" onclick="toggleQA(this)">
                    Q: Can you give me a guide for using {{ $brandName }} . coupon codes?
                    <span class="qa-icon">+</span>
                </button>
                <div class="qa-answer">A: Copy the code that fits your order, add items to cart at {{ $brandName }} , paste the code in the promo field, and apply before you pay.</div>
            </div>
            <div class="qa-item">
                <button class="qa-question" type="button" onclick="toggleQA(this)">
                    Q: How to use {{ $brandName }} . coupon codes?
                    <span class="qa-icon">+</span>
                </button>
                <div class="qa-answer">A: Pick a coupon on this page, click GET CODE or GET DEAL, copy if needed, and complete checkout at the store.</div>
            </div>
            <div class="qa-item">
                <button class="qa-question" type="button" onclick="toggleQA(this)">
                    Q: How often does {{ $brandName }} . release a new coupon?
                    <span class="qa-icon">+</span>
                </button>
                <div class="qa-answer">A: New offers can appear anytime. Check this page regularly — we update verified {{ $brandName }} coupons as soon as they are available.</div>
            </div>
        </section>
    </div>
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
        <div class="t3-popup-banner">
            <div>
                @if($campaign->brand)
                    <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}" class="t3-popup-logo" loading="lazy">
                @elseif($campaign->logo)
                    <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}" class="t3-popup-logo" loading="lazy">
                @else
                    <img src="{{ asset('images/default-brand.svg') }}" alt="{{ $brandName }}" class="t3-popup-logo" loading="lazy">
                @endif
                <h3 class="t3-popup-title" id="coupon-modal-title">
                    {{ $brandName }} Promotion
                </h3>
                <p class="t3-popup-subtitle">
                    A Little Discount, A Lot of Joy—Save Now with {{ $brandName }} Promotion.
                </p>
            </div>
        </div>
        <div class="t3-popup-body">
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
        <div class="t3-popup-banner">
            <div>
                @if($campaign->brand)
                    <img src="{{ $campaign->brand->image_url }}" alt="{{ $brandName }}" class="t3-popup-logo" loading="lazy">
                @elseif($campaign->logo)
                    <img src="{{ asset('storage/' . $campaign->logo) }}" alt="{{ $campaign->title }}" class="t3-popup-logo" loading="lazy">
                @else
                    <img src="{{ asset('images/default-brand.svg') }}" alt="{{ $brandName }}" class="t3-popup-logo" loading="lazy">
                @endif
                <h3 class="t3-popup-title" id="all-codes-modal-title">All {{ $brandName }} Coupon Codes</h3>
                <p class="t3-popup-subtitle">Copy any code below and apply at checkout.</p>
            </div>
        </div>
        <div class="t3-popup-body">
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
        this.style.background = '#059669';
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
            source_template: 'template4'
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

    function applyCouponFilter(current) {
        rows.forEach(row => {
            const type = row.dataset.type;
            let show = true;

            if (current === 'codes' || current === 'verified') {
                show = type === 'code';
            } else if (current === 'deals') {
                show = type === 'deal';
            }

            row.style.display = show ? 'grid' : 'none';
        });
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-pressed', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-pressed', 'true');
            applyCouponFilter(tab.dataset.tab || 'all');
        });
    });

    const activeTab = document.querySelector('.filter-pill.active');
    applyCouponFilter(activeTab ? (activeTab.dataset.tab || 'verified') : 'verified');
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
