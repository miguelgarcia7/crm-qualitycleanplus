<?php

namespace App\Domain\Marketing\Support;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Language for the marketing site (English at `/`, Spanish at `/es/...`,
 * marketing-site-audit.md D1). Every page exists under two route names —
 * `marketing.{page}` and `marketing.es.{page}` — with the legacy site's Spanish
 * slugs kept so existing links and rankings carry over. Views receive this as
 * `$site` (AppServiceProvider view composer) and build every link through it,
 * so a Spanish page links to Spanish pages.
 */
final class SiteLocale
{
    /** @var array<string, string> locale => name in its own language */
    public const LOCALES = ['en' => 'English', 'es' => 'Español'];

    public function current(): string
    {
        return App::getLocale() === 'es' ? 'es' : 'en';
    }

    public function isSpanish(): bool
    {
        return $this->current() === 'es';
    }

    /** `$page` is the English route name without `marketing.` (e.g. `contact.business`). */
    public function routeName(string $page, ?string $locale = null): string
    {
        return ($locale ?? $this->current()) === 'es' ? "marketing.es.{$page}" : "marketing.{$page}";
    }

    /**
     * A marketing page's URL in the current language (or `$locale`).
     *
     * @param  mixed  $parameters  route parameters, e.g. a JobPosting
     */
    public function route(string $page, mixed $parameters = [], ?string $locale = null, bool $absolute = false): string
    {
        return route($this->routeName($page, $locale), $parameters, $absolute);
    }

    /** Whether the current request is `$page` or one of its sub-pages, in either language. */
    public function is(string $page): bool
    {
        return request()->routeIs($this->routeName($page), $this->routeName($page).'.*');
    }

    /**
     * This page in `$locale` — what the language switcher and hreflang tags
     * point at. Null when the current route has no counterpart.
     */
    public function alternate(string $locale): ?string
    {
        $route = request()->route();
        $name = $route?->getName();

        if ($route === null || $name === null || ! str_starts_with($name, 'marketing.')) {
            return null;
        }

        $page = Str::after($name, str_starts_with($name, 'marketing.es.') ? 'marketing.es.' : 'marketing.');
        $target = $this->routeName($page, $locale);

        return Route::has($target) ? route($target, $route->parameters(), true) : null;
    }

    /** Open Graph locale for the current language. */
    public function ogLocale(): string
    {
        return $this->isSpanish() ? 'es_US' : 'en_US';
    }
}
