<?php

namespace App\Domain\Marketing\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The rendered sitemap.xml, built once and reused. The only data it reads is
 * the published job postings, so any posting change forgets it
 * (AppServiceProvider); the expiry is a safety net for what a deploy changes
 * (a new page, a renamed route) without a cache clear.
 */
final class SitemapCache
{
    private const TTL_SECONDS = 86400;

    /** @param  Closure(): string  $build */
    public static function remember(string $origin, Closure $build): string
    {
        return Cache::remember(self::key($origin), self::TTL_SECONDS, $build);
    }

    /** Forget the copy for every origin the site answers on. */
    public static function forget(): void
    {
        foreach (['http', 'https'] as $scheme) {
            Cache::forget(self::key($scheme.'://'.config('domains.main')));
        }
    }

    /** URLs in the sitemap are absolute, so each scheme + host gets its own copy. */
    private static function key(string $origin): string
    {
        return 'marketing.sitemap.'.$origin;
    }
}
