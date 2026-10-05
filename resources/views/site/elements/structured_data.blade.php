{{-- schema.org business details for search results (name, address, phone, hours,
     social profiles). Included on the home page in both languages. --}}
@push('meta_data')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'EmploymentAgency',
    'name' => 'Quality Cleaning Plus, Inc.',
    'description' => __('site/home.meta.description'),
    'url' => $site->route('home', [], null, true),
    'logo' => url('/images/quality-cleaning-plus-logo.png'),
    'image' => url('/images/social-media-website-2.png'),
    'telephone' => '+1-214-271-5595',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => '1720 Regal Row, Suite 126',
        'addressLocality' => 'Dallas',
        'addressRegion' => 'TX',
        'postalCode' => '75235',
        'addressCountry' => 'US',
    ],
    'openingHoursSpecification' => [
        ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday'], 'opens' => '09:30', 'closes' => '17:30'],
        ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Friday', 'opens' => '09:30', 'closes' => '18:00'],
    ],
    'sameAs' => [
        'https://www.facebook.com/profile.php?id=100071842145369',
        'https://www.instagram.com/qualitycleaningplus/',
        'https://www.tiktok.com/@qualitycleaningplus',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG) !!}
</script>
@endpush
