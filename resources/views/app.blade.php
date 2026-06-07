<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-skin="sage">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">

    @viteReactRefresh
    @vite(['resources/js/admin/app.tsx'])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
