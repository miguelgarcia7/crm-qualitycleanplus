@push('meta_data')
<title>{{ $meta_title }} | {{ config('app.name', '') }}</title>
	<meta name="description" content="{{ $meta_description }}">
	@if (! empty($noindex))
	<meta name="robots" content="noindex, follow">
	@endif
	<link rel="canonical" href="{{ url()->current() }}" />
	{{-- Each page in both languages (SiteLocale::alternate); English is the default. --}}
	@foreach (array_keys(\App\Domain\Marketing\Support\SiteLocale::LOCALES) as $hreflang)
		@if ($alternate = $site->alternate($hreflang))
	<link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $alternate }}" />
		@endif
	@endforeach
	@if ($default = $site->alternate('en'))
	<link rel="alternate" hreflang="x-default" href="{{ $default }}" />
	@endif
	<meta property="og:locale" content="{{ $site->ogLocale() }}" />
	<meta property="og:type" content="website" />
	<meta property="og:title" content="{{ $meta_title }} | {{ config('app.name', '') }}" />
	<meta property="og:description" content="{{ $meta_description }}" />
	<meta property="og:url" content="{{ url()->current() }}" />
	<meta property="og:site_name" content="{{ config('app.name', '') }}" />
	<meta property="og:image" content="{{ url($meta_image ?? '/images/social-media-website-2.png') }}" />
	<meta name="twitter:card" content="summary_large_image" />
	<meta name="twitter:title" content="{{ $meta_title }} | {{ config('app.name', '') }}" />
	<meta name="twitter:description" content="{{ $meta_description }}" />
	<meta name="twitter:image" content="{{ url($meta_image ?? '/images/social-media-website-2.png') }}" />
@endpush
