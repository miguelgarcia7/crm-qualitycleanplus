@push('meta_data')
<title>{{ $meta_title }} | {{ config('app.name', '') }} </title>
	<meta name="description" content="{{ $meta_description }}">
	<link rel="canonical" href="https://www.qualitycleanplus.com/" />
	<meta property="og:locale" content="en_US" />
	<meta property="og:type" content="website" />
	<meta property="og:title" content="{{ $meta_title }} | {{ config('app.name', '') }}" />
	<meta property="og:description" content="{{ $meta_description }}" />
	<meta property="og:url" content="{{ url()->full() }}" />
	<meta property="og:site_name" content="{{ config('app.name', '') }}" />
	<meta property="og:image" content="{{ $meta_image ?? "https://www.qualitycleanplus.com/images/social-media-website-2.png" }}" />
	<meta name="twitter:title" content="{{ $meta_title }} | {{ config('app.name', '') }}" />
	<meta name="twitter:description" content="{{ $meta_description }}" />
	<meta name="twitter:site" content="@QualityCleaningPlus" />
	<meta name="twitter:image" content="{{ $meta_image ?? "https://www.qualitycleanplus.com/images/social-media-website-2.png" }}" />
	<meta name="twitter:creator" content="@QualityCleaningPlus" />
	<meta property="DC.date.issued" content="2022-1-1T12:00:00+00:00" />
@endpush