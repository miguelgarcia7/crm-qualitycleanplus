<!doctype html>
<html class="no-js" lang="{{ $site->current() }}">
<head>
    <script>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');</script>
    @include('site.elements.browser_data')

    @stack('meta_data')
	<!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">

    <!-- Styles -->
    @vite('resources/css/site/app.scss')
    @yield('head_js')

    @yield('css_after')

    @include('site.elements.google_analytics')

</head>
<body>

    <main id="content" class="main">

        @include('site.layouts/navigation')

        @yield('content')

        @include('site.layouts/footer')

    </main>

    <!-- Scripts -->
    @vite('resources/js/site/app.js')

    @yield('js_after')
    @stack('scripts')

</body>
</html>
