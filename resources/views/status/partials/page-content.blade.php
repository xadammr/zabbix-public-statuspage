<header>
    <div>
        <h1>Service Status</h1>
        @php
            $generatedAt = $statusPage['generated_at']->copy()->timezone(config('app.timezone'));
            $generatedAtIso = $generatedAt->toIso8601String();
        @endphp
        <p class="last-update muted" data-refresh-highlight>
            <span
                class="refresh-progress"
                data-page-refresh-progress
                role="progressbar"
                aria-label="Status refresh progress"
                aria-valuemin="0"
                aria-valuemax="15"
                aria-valuenow="0"
                title="Status refresh progress"
            ></span>
            <time
                data-last-updated-at="{{ $generatedAtIso }}"
                datetime="{{ $generatedAtIso }}"
                title="{{ $generatedAt->format('Y-m-d H:i:s') }}"
            >
                Polling...
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
            <span class="footer-debug-item" aria-label="Request IP address">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M12 20h.01"></path>
                    <path d="M8 16.5a6 6 0 0 1 8 0"></path>
                    <path d="M4.5 12a11 11 0 0 1 15 0"></path>
                    <path d="M2 8a16 16 0 0 1 20 0"></path>
                </svg>
                <span>{{ $statusDebug['request_ip'] }}</span>
            </span>
            <span class="footer-debug-item" aria-label="Real IP header">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M20 10c0 4.8-8 12-8 12S4 14.8 4 10a8 8 0 1 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span>{{ $statusDebug['real_ip'] }}</span>
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
