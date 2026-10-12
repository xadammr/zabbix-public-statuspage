<div>
    <h3>{{ $service['name'] }}</h3>
    @if ($service['description'])
        <p class="muted">{{ $service['description'] }}</p>
    @endif
</div>

<div class="service-actions">
    @if ($alertStartedAt ?? null)
        <span
            class="alert-duration"
            data-alert-started-at="{{ $alertStartedAt }}"
            aria-label="Alert active duration"
            title="Alert active duration"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="9"></circle>
                <path d="M12 7v5l3 2"></path>
            </svg>
            <span data-alert-duration>0s</span>
        </span>
    @endif

    @unless ($hideState ?? false)
        <span class="state {{ $service['severity']['class'] }}">
            {{ $service['severity']['label'] }}
        </span>
    @endunless

    @if (! ($hideLink ?? false) && ($service['public_url'] ?? null))
        <a
            class="service-link"
            href="{{ $service['public_url'] }}"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Open {{ $service['name'] }}"
            title="Open {{ $service['name'] }}"
        >
            <svg aria-hidden="true" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M2 12h20"></path>
                <path d="M12 2a15.3 15.3 0 0 1 0 20"></path>
                <path d="M12 2a15.3 15.3 0 0 0 0 20"></path>
            </svg>
        </a>
    @endif
</div>
