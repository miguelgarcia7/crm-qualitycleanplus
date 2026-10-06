<!doctype html>
<html class="no-js" lang="{{ $site->current() }}">
<head>
    <script>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');</script>
    @include('site.elements.browser_data')

    @stack('meta_data')
	<!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
