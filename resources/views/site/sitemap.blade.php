{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
@foreach ($locales as $locale)
    <url>
        <loc>{{ $site->route($entry['page'], $entry['parameters'], $locale, true) }}</loc>
@foreach ($locales as $alternate)
        <xhtml:link rel="alternate" hreflang="{{ $alternate }}" href="{{ $site->route($entry['page'], $entry['parameters'], $alternate, true) }}" />
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $site->route($entry['page'], $entry['parameters'], 'en', true) }}" />
@if ($entry['updated'])
        <lastmod>{{ $entry['updated']->toDateString() }}</lastmod>
@endif
    </url>
@endforeach
@endforeach
</urlset>
