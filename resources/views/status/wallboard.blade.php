<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ config('statuspage.theme_color') }}">
        <title>{{ config('statuspage.wallboard_title') }}</title>
        @if (config('statuspage.favicon_url'))
            <link rel="icon" href="{{ config('statuspage.favicon_url') }}">
        @endif
        @foreach (config('statuspage.custom_css_urls') as $customCssUrl)
            <link rel="stylesheet" href="{{ $customCssUrl }}">
        @endforeach
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
