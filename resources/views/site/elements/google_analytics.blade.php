{{-- Google Analytics 4 — only on the real public site (Seo::analyticsId). --}}
@if ($gaId = \App\Domain\Marketing\Support\Seo::analyticsId())
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($gaId));
    </script>
@endif
