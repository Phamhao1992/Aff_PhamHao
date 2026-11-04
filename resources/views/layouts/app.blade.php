<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        use App\Support\MetaTag;

        $seoTitleSuffix = \App\Support\AdminSettings::get('seo_title_suffix', '- ' . config('app.name'));
        $baseTitle = trim($__env->yieldContent('title', config('app.name')));
        $finalTitle = $baseTitle;
        if ($seoTitleSuffix !== '' && ! str_contains($baseTitle, $seoTitleSuffix)) {
            $finalTitle = trim($baseTitle . ' ' . $seoTitleSuffix);
        }
        $pageDescription = trim($__env->yieldContent('description', \App\Support\AdminSettings::get('seo_meta_description_default', 'Best coupons, deals and store reviews.')));
        $ogTitleOverride = trim($__env->yieldContent('og_title', ''));
        $ogDescriptionOverride = trim($__env->yieldContent('og_description', ''));
        $documentTitle = MetaTag::plain($finalTitle);
        $metaTitle = MetaTag::plain($ogTitleOverride !== '' ? $ogTitleOverride : $finalTitle);
        $metaDescription = MetaTag::plain($ogDescriptionOverride !== '' ? $ogDescriptionOverride : $pageDescription);
        $defaultMetaDescription = \App\Support\AdminSettings::get('seo_meta_description_default', 'Best coupons, deals and store reviews.');
        $defaultOgImage = \App\Support\AdminSettings::get('seo_og_image_default', '');
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="verify-admitad" content="4455f2e7bb" />
    <meta name='impact-site-verification' value='35f2fb10-d495-4208-b451-b9f1e79b72a9'>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="dns-prefetch" href="https://www.googletagmanager.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $usesMagazineChrome = \App\Support\MagazineLayout::usesMagazineChrome(); @endphp
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    @yield('head')
    @hasSection('og_image')
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:image" content="@yield('og_image')">
    <meta property="og:site_name" content="{{ MetaTag::plain(config('app.name')) }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="@yield('og_image')">
    
    @else
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(!empty($defaultOgImage))
    <meta property="og:image" content="{{ $defaultOgImage }}">
    @endif
    <meta property="og:site_name" content="{{ MetaTag::plain(config('app.name')) }}">
    <meta name="twitter:card" content="{{ !empty($defaultOgImage) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if(!empty($defaultOgImage))
    <meta name="twitter:image" content="{{ $defaultOgImage }}">
    @endif
    @endif
    @php
        $organizationSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => MetaTag::plain(config('app.name')),
            'url' => config('app.url'),
            'description' => MetaTag::plain($defaultMetaDescription),
        ];
    @endphp
    <script type="application/ld+json">{{ json_encode($organizationSchema) }}</script>
    @include('partials.tracking-head')
    @stack('head')
    <style>
        :root {
            --bg: #eceae4;
            --surface: #ffffff;
            --surface-hover: #f5f3ee;
            --text: #0f0f14;
            --text-dark: #0f0f14;
            --text-muted: #5a5a6e;
            --primary: #198754;
            --primary-dark: #157347;
            --accent: #198754;
            --accent-hover: #157347;
            --accent-rose: #20c997;
            --border: rgba(15, 15, 20, 0.1);
            --radius: 12px;
            --radius-sm: 8px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', system-ui, sans-serif;
            font-size: 16px;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        body.magazine-site {
            font-family: 'Poppins', system-ui, sans-serif;
            font-size: 17px;
            background: #eceae4;
            color: #0f0f14;
            --bg: #eceae4;
            --surface: #ffffff;
            --surface-hover: #f5f3ee;
            --text: #0f0f14;
            --text-muted: #5a5a6e;
            --primary: #198754;
            --primary-dark: #157347;
            --accent: #198754;
            --accent-hover: #157347;
            --accent-rose: #20c997;
            --border: rgba(15, 15, 20, 0.1);
        }
        .font-heading { font-family: 'Poppins', system-ui, sans-serif; }

        .logo {
            font-family: 'Poppins', system-ui, sans-serif;
            font-weight: 700;
            font-size: 1.35rem;
            color: var(--text);
            text-decoration: none;
            letter-spacing: -0.02em;
        }
        .logo span {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-rose) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: var(--accent);
        }

        /* Main */
        main { flex: 1; }

        /* Pagination */
        .pagination-nav { margin-top: 2rem; }
        .pagination-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 0.4rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .pagination-list li { display: inline-flex; }
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            padding: 0.5rem 0.75rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            transition: border-color 0.2s, color 0.2s;
        }
        .pagination-item:hover:not(.pagination-disabled):not(.pagination-current) {
            border-color: var(--accent);
            color: var(--accent);
        }
        .pagination-disabled, .pagination-current {
            color: var(--text-muted);
            cursor: default;
            pointer-events: none;
        }
        .pagination-current {
            background: var(--surface-hover);
            border-color: var(--accent);
            color: var(--accent);
            pointer-events: none;
        }
        .pagination-ellipsis {
            border: none;
            background: transparent;
        }
        .pagination-info {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Cookie consent bar */
        .cookie-consent {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #0f172a;
            color: #fff;
            padding: 1rem 1.5rem;
            z-index: 999;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
        }
        .cookie-consent[hidden] { display: none; }
        .cookie-consent-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .cookie-consent p { margin: 0; font-size: 0.9rem; flex: 1; min-width: 200px; }
        .cookie-consent a { color: #7dd3fc; text-decoration: underline; text-underline-offset: 2px; }
        .cookie-consent a:hover { color: #bae6fd; }
        .cookie-consent-btn {
            padding: 0.5rem 1.25rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-rose) 100%);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .cookie-consent-btn:hover {
            filter: brightness(1.06);
            box-shadow: 0 4px 14px rgba(25, 135, 84, 0.35);
        }
    </style>
    @include('partials.site-chrome-styles')
    @if($usesMagazineChrome)
        @include('partials.styles.magazine-chrome')
        @include('partials.styles.magazine-site')
    @endif
    @stack('styles')
</head>
<body @if($usesMagazineChrome) class="magazine-site" @endif>
@include('partials.tracking-body')
    @include('partials.site-header')

    <main>
        @yield('content')
    </main>

    @include('partials.site-footer')

    <div id="cookie-consent" class="cookie-consent" role="dialog" aria-label="Cookie notice" hidden>
        <div class="cookie-consent-inner">
            <p>We use cookies to improve your experience and analyze site traffic. See our <a href="{{ url('/cookie-policy') }}">Cookie Policy</a> and <a href="{{ url('/privacy') }}">Privacy Policy</a>. By continuing you agree to their use.</p>
            <button type="button" class="cookie-consent-btn" data-dismiss>OK</button>
        </div>
    </div>
    <script>
        window.addEventListener('load', function () {
            if (localStorage.getItem('cookie-consent-accepted')) return;
            var bar = document.getElementById('cookie-consent');
            if (!bar) return;
            var showBar = function () {
                bar.removeAttribute('hidden');
                bar.querySelector('[data-dismiss]')?.addEventListener('click', function() {
                    localStorage.setItem('cookie-consent-accepted', '1');
                    bar.setAttribute('hidden', '');
                });
            };
            if ('requestIdleCallback' in window) {
                requestIdleCallback(showBar, { timeout: 2000 });
            } else {
                setTimeout(showBar, 0);
            }
        }, { once: true });
    </script>

    @include('partials.back-to-top')

    @stack('scripts')
    <script>
    window.addEventListener('load', function () {
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-FF4K1DWWT7');
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=G-FF4K1DWWT7';
        document.head.appendChild(s);
    }, { once: true });
    </script>
</body>
</html>
