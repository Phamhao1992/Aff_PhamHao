<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @php
        $campaignSlug = $campaign->slug;
        $backgroundImage = $campaign->background_image ? \Illuminate\Support\Facades\Storage::disk('public')->url($campaign->background_image) : null;
        $productImages = $campaign->key_product_images ?? [];
        $logoUrl = $campaign->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($campaign->logo) : ($campaign->brand?->image_url ?? asset('images/default-brand.svg'));
    @endphp
    <title>{{ \App\Support\MetaTag::plain($campaign->title) }}</title>
    <meta name="description" content="{{ \App\Support\MetaTag::plain($campaign->subtitle ?? strip_tags($campaign->intro ?? '')) }}">
    <meta name="robots" content="index, follow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    @include('partials.site-chrome-styles')
    @if(config('app.ga4_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga4_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('app.ga4_id') }}');
    </script>
    @endif

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #7c3aed;
            --primary-dark: #a78bfa;
            --primary-light: #6d28d9;
            --accent: #6d28d9;
            --text-dark: #0f172a;
            --text-light: #475569;
            --bg-page: #e8edf3;
            --bg-overlay: rgba(2, 6, 23, 0.55);
            --shadow: 0 10px 22px -16px rgba(15, 23, 42, 0.28);
            --shadow-lg: 0 26px 60px -34px rgba(2, 6, 23, 0.55);
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            overflow-x: hidden;
            background: var(--bg-page);
        }
        a { text-decoration: none; color: inherit; }

        /* Background với overlay */
        .page-wrapper {
            position: relative;
            min-height: 100vh;
            @if($backgroundImage)
            background-image: url('{{ $backgroundImage }}');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            @else
            background:
                radial-gradient(70% 90% at 8% 10%, rgba(124, 58, 237, 0.22) 0%, transparent 55%),
                radial-gradient(70% 90% at 92% 88%, rgba(96, 165, 250, 0.22) 0%, transparent 55%),
                linear-gradient(135deg, #0f172a 0%, #5b21b6 48%, #1e1b4b 100%);
            @endif
        }
        .page-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                linear-gradient(180deg, rgba(2, 6, 23, 0.2) 0%, rgba(2, 6, 23, 0.55) 100%);
            z-index: 1;
        }

        /* Container */
        .container {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 0 auto;
            padding: 48px 20px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 60px;
        }
        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
        }
        .title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 16px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            line-height: 1.2;
        }
        .subtitle {
            font-size: clamp(1.1rem, 2vw, 1.5rem);
            color: rgba(255, 255, 255, 0.95);
            font-weight: 400;
            max-width: 800px;
            margin: 0 auto;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
        }
        .disclosure-line {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 16px;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
        }
        .disclosure-line a {
            color: #93c5fd;
            text-decoration: underline;
        }

        /* Content Section */
        .content-section {
            background: rgba(248, 250, 252, 0.92);
            border-radius: 22px;
            padding: 46px 40px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 40px;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(148, 163, 184, 0.3);
            overflow: hidden;
        }
        .intro {
            font-size: 1.125rem;
            color: var(--text-light);
            line-height: 1.8;
            margin-bottom: 40px;
            max-width: 920px;
            margin-left: auto;
            margin-right: auto;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .intro p {
            margin-bottom: 16px;
        }
        .intro h1, .intro h2, .intro h3 {
            color: var(--text-dark);
            line-height: 1.25;
            margin: 1rem 0 0.5rem;
        }
        .intro a {
            color: var(--primary-dark);
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .intro img,
        .intro video,
        .intro iframe,
        .intro table,
        .intro pre,
        .intro code {
            max-width: 100%;
        }
        .intro table {
            display: block;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .intro pre {
            overflow-x: auto;
        }

        /* Product Images Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin: 40px 0;
        }
        .product-item {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.22);
        }
        .product-item:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        .product-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            display: block;
        }
        .product-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
            padding: 20px;
            color: #ffffff;
        }

        /* CTA Button */
        .cta-section {
            text-align: center;
            margin-top: 50px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            padding: 20px 50px;
            border-radius: 50px;
            box-shadow: 0 14px 30px -16px rgba(2, 6, 23, 0.65);
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cta-button:hover {
            transform: scale(1.05);
            filter: brightness(1.06) saturate(1.03);
            box-shadow: 0 18px 38px -16px rgba(2, 6, 23, 0.7);
        }
        .cta-button:active {
            transform: scale(0.98);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container { padding: 20px 15px; }
            .content-section { padding: 30px 20px; }
            .product-grid { grid-template-columns: 1fr; gap: 20px; }
            .product-image { height: 250px; }
            .cta-button { padding: 16px 40px; font-size: 1.1rem; }
        }

        /* Loading animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .content-section, .product-item {
            animation: fadeInUp 0.6s ease-out;
        }
    </style>
    @include('partials.tracking-head')
</head>
<body>
@include('partials.tracking-body')
    <div class="page-wrapper">
        <div class="container">
            <header class="header">
                <img src="{{ $logoUrl }}" alt="{{ $campaign->title }}" class="logo">
                <h1 class="title">{{ $campaign->title }}</h1>
                @if($campaign->subtitle)
                <p class="subtitle">{{ $campaign->subtitle }}</p>
                @endif
                <p class="disclosure-line">We may earn a commission when you use our links, at no extra cost to you. <a href="{{ url('/affiliate-disclosure') }}" target="_blank" rel="noopener">See our disclosure</a>.</p>
            </header>

            <div class="content-section">
                <div class="cta-section" style="margin-top: 0; margin-bottom: 34px;">
                    <a href="{{ route('click.redirect', ['slug' => $campaignSlug]) }}" class="cta-button" target="_blank" rel="nofollow sponsored noopener">
                        {{ $campaign->cta_text ?? 'Nhận ngay' }}
                    </a>
                </div>

                @if($campaign->intro)
                    @php
                        $intro = (string) $campaign->intro;
                        $hasHtml = $intro !== strip_tags($intro);
                    @endphp
                    <div class="intro">
                        @if($hasHtml)
                            {!! $intro !!}
                        @else
                            {!! nl2br(e($intro)) !!}
                        @endif
                    </div>
                @endif

                @if(count($productImages) > 0)
                <div class="product-grid">
                    @foreach($productImages as $image)
                        @php
                            $imageUrl = is_string($image) ? \Illuminate\Support\Facades\Storage::disk('public')->url($image) : (\Illuminate\Support\Facades\Storage::disk('public')->url($image['path'] ?? $image) ?? '');
                        @endphp
                        @if($imageUrl)
                        <div class="product-item">
                            <img src="{{ $imageUrl }}" alt="Product" class="product-image" loading="lazy">
                        </div>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Track page view time
        let startTime = Date.now();
        window.addEventListener('beforeunload', function() {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'page_view_time', {
                    'value': Math.round((Date.now() - startTime) / 1000)
                });
            }
        });
    </script>

    @include('partials.back-to-top')
</body>
</html>
