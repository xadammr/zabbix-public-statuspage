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
                    @php
                        $generatedAt = $statusPage['generated_at']->copy()->timezone(config('app.timezone'));
                        $updatedAge = preg_replace_callback('/^([1-9])\b/', fn (array $matches) => [
                            '1' => 'one',
                            '2' => 'two',
                            '3' => 'three',
                            '4' => 'four',
                            '5' => 'five',
                            '6' => 'six',
                            '7' => 'seven',
                            '8' => 'eight',
                            '9' => 'nine',
                        ][$matches[1]], $generatedAt->diffForHumans(['parts' => 1]));
                    @endphp
                    <p class="last-update muted">
                        <span
                            class="refresh-progress"
                            data-page-refresh-progress
                            role="progressbar"
                            aria-label="Page refresh progress"
                            aria-valuemin="0"
                            aria-valuemax="60"
                            aria-valuenow="0"
                            title="Page refresh progress"
                        ></span>
                        Last updated:
                        <time
                            datetime="{{ $generatedAt->toIso8601String() }}"
                            title="{{ $generatedAt->format('Y-m-d H:i:s') }}"
                        >
                            {{ $updatedAge }}
                        </time>
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
