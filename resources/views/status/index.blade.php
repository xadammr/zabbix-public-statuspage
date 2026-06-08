<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>spd.ltd - service status</title>
        @if (config('services.plausible.domain'))
            <script
                defer
                data-domain="{{ config('services.plausible.domain') }}"
                src="{{ config('services.plausible.script_url') }}"
            ></script>
        @endif
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
                @isset($statusDebug)
                    <p class="footer-debug muted">
                        <span class="footer-debug-item" aria-label="Client IP address">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M12 20h.01"></path>
                                <path d="M8 16.5a6 6 0 0 1 8 0"></path>
                                <path d="M4.5 12a11 11 0 0 1 15 0"></path>
                                <path d="M2 8a16 16 0 0 1 20 0"></path>
                            </svg>
                            <span>{{ $statusDebug['client_ip'] }}</span>
                        </span>
                        <span class="footer-debug-item" aria-label="Shown sections">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <span>{{ implode(', ', $statusDebug['shown_sections']) ?: 'none' }}</span>
                        </span>
                        <span class="footer-debug-item" aria-label="Hidden sections">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M10.7 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-2 3"></path>
                                <path d="M6.6 6.6C3.7 8.5 2 12 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                                <path d="M3 3l18 18"></path>
                            </svg>
                            <span>{{ implode(', ', $statusDebug['hidden_sections']) ?: 'none' }}</span>
                        </span>
                    </p>
                @endisset
            </footer>
        </main>
    </body>
</html>
