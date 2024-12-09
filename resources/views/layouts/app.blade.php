<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LlaravelLlama</title>
    <!-- CSS Dependencies -->
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/font-awesome/css/all.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/highlight.js/styles/default.min.css') }}">
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body>
    @yield('content')
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/showdown/showdown.min.js') }}"></script>
    <script src="{{ asset('vendor/highlight.js/highlight.min.js') }}"></script>
    <script src="{{ asset('vendor/highlight.js/languages/javascript.min.js') }}"></script>
    <script src="{{ asset('vendor/highlight.js/languages/php.min.js') }}"></script>
    <script src="{{ asset('vendor/highlight.js/languages/python.min.js') }}"></script>
    <script>
        window.DEBUG_PANEL = {{ config('app.debug') ? 'true' : 'false' }};
    </script>
    @yield('scripts')
</body>
@include('chat.partials.debug', ['debugEnabled' => config('app.debug')])

</html>
