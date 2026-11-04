<?php

namespace App\Support;

use App\Models\SiteContent;
use Illuminate\Support\Facades\Crypt;

class AdminSettings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return SiteContent::get('settings.' . $key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        SiteContent::set('settings.' . $key, $value);
    }

    public static function forget(string $key): void
    {
        SiteContent::set('settings.' . $key, null);
    }

    public static function getEncrypted(string $key, ?string $default = null): ?string
    {
        $raw = SiteContent::get('settings.secure.' . $key);
        if (! is_string($raw) || trim($raw) === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function setEncrypted(string $key, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            SiteContent::set('settings.secure.' . $key, '');

            return;
        }

        SiteContent::set('settings.secure.' . $key, Crypt::encryptString($value));
    }

    /**
     * @return list<string>
     */
    public static function getEncryptedLines(string $key, ?string $default = null): array
    {
        $raw = static::getEncrypted($key, $default);
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $lines = preg_split('/\R/', $raw) ?: [];

        return array_values(array_filter(array_map(
            static fn ($line) => trim((string) $line),
            $lines
        ), static fn (string $line) => $line !== ''));
    }

    /**
     * @param  list<string>|string|null  $lines
     */
    public static function setEncryptedLines(string $key, array|string|null $lines): void
    {
        if ($lines === null) {
            static::setEncrypted($key, null);

            return;
        }

        if (is_string($lines)) {
            $lines = preg_split('/\R/', $lines) ?: [];
        }

        $normalized = array_values(array_filter(array_map(
            static fn ($line) => trim((string) $line),
            $lines
        ), static fn (string $line) => $line !== ''));

        if ($normalized === []) {
            static::setEncrypted($key, null);

            return;
        }

        static::setEncrypted($key, implode("\n", $normalized));
    }

    /**
     * @return list<string>
     */
    public static function geminiApiKeys(): array
    {
        $keys = static::getEncryptedLines('gemini_api_key');
        if ($keys !== []) {
            return $keys;
        }

        $fallback = trim((string) config('gemini.api_key', ''));

        return $fallback !== '' ? [$fallback] : [];
    }

    /**
     * @return list<string>
     */
    public static function apifyTokens(): array
    {
        return static::getEncryptedLines('apify_token');
    }

    public static function siteLogoPath(): ?string
    {
        $path = trim((string) static::get('site_logo_path', ''));

        return $path !== '' ? $path : null;
    }

    public static function siteLogoUrl(): ?string
    {
        $path = static::siteLogoPath();
        if ($path === null) {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    /** Google Ads / GTM / meta verification — chèn trước </head>. */
    public static function trackingHeadCode(): string
    {
        return trim((string) static::get('tracking_head_code', ''));
    }

    /** Snippet noscript / body — chèn ngay sau <body>. */
    public static function trackingBodyCode(): string
    {
        return trim((string) static::get('tracking_body_code', ''));
    }
}
