<?php

namespace App\Domain\Marketing\Support;

/**
 * Whether this deployment is the real public site. Only production, outside
 * demo mode, invites search engines (robots.txt, sitemap) or loads analytics —
 * local, staging and the demo stay out of the index and out of the numbers.
 */
final class Seo
{
    public static function indexable(): bool
    {
        return app()->isProduction() && ! config('demo.enabled');
    }

    /** The Google Analytics measurement id, or null when analytics must not load. */
    public static function analyticsId(): ?string
    {
        $id = config('services.google_analytics.measurement_id');

        return self::indexable() && is_string($id) && $id !== '' ? $id : null;
    }
}
