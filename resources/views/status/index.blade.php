<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>spd.ltd - service status</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main>
            <header>
                <div>
                    <h1>Service Status</h1>
                    @php($generatedAt = $statusPage['generated_at']->copy()->timezone(config('app.timezone')))
                    <p class="last-update muted">
                        Updated
                        <time datetime="{{ $generatedAt->toIso8601String() }}">
                            {{ $generatedAt->format('H:i:s') }}
                        </time>
                        @if (isset($statusPage['cache']['next_refresh_at']))
                            <span
                                class="next-refresh"
                                data-next-refresh-at="{{ $statusPage['cache']['next_refresh_at']->toIso8601String() }}"
                            >
                                Next pull in <span data-countdown>...</span>
                            </span>
                        @endif
                    </p>
                </div>

                @if ($statusPage['cache']['is_stale'] ?? false)
                    @include('status.partials.stale-cache-warning', ['cache' => $statusPage['cache']])
                @endif

                @include('status.partials.summary', ['summary' => $statusPage['summary']])
            </header>

            @foreach ($statusPage['sections'] as $section)
                @include('status.partials.section', ['section' => $section])
            @endforeach

            <footer>
                <p class="muted">
                    &copy; {{ date('Y') }} SPD Ltd. All rights reserved.
                </p>
            </footer>
        </main>
    </body>
</html>
