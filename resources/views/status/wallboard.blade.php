<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1a1a1a">
        <title>spd.ltd - operations wallboard</title>
        <link rel="icon" href="/images/favicon.ico" sizes="any">
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    </head>
    <body class="wallboard">
        <main data-status-page-content data-status-fragment-url="/wallboard/status-fragment">
            @include('status.partials.page-content', [
                'statusPage' => $statusPage,
                'wallboard' => true,
            ])
        </main>
    </body>
</html>
