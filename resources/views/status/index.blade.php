<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#d92d20">
        <title>spd.ltd - service status</title>
        <link rel="icon" href="/images/favicon.ico" sizes="any">
        <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
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
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <main data-status-page-content>
            @include('status.partials.page-content', [
                'statusDebug' => $statusDebug ?? null,
                'statusPage' => $statusPage,
            ])
        </main>
    </body>
</html>
