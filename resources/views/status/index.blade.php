<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ config('statuspage.theme_color') }}">
        <title>{{ config('statuspage.title') }}</title>
        @if (config('statuspage.favicon_url'))
            <link rel="icon" href="{{ config('statuspage.favicon_url') }}">
        @endif
        <link rel="manifest" href="/site.webmanifest">
        @if (config('services.plausible.domain'))
            <script
                defer
                data-domain="{{ config('services.plausible.domain') }}"
                src="{{ config('services.plausible.script_url') }}"
            ></script>
            <script>
                window.plausible=window.plausible||function(){(plausible.q=plausible.q||[]).push(arguments)},plausible.init=plausible.init||function(i){plausible.o=i||{}};
                plausible.init()
            </script>
        @endif
        @foreach (config('statuspage.custom_css_urls') as $customCssUrl)
            <link rel="stylesheet" href="{{ $customCssUrl }}">
        @endforeach
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <main data-status-page-content data-status-fragment-url="/status-fragment">
            @include('status.partials.page-content', [
                'statusDebug' => $statusDebug ?? null,
                'statusPage' => $statusPage,
            ])
        </main>
    </body>
</html>
