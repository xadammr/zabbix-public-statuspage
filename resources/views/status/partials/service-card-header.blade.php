<div>
    <h3>{{ $service['name'] }}</h3>
    @if ($service['description'])
        <p class="muted">{{ $service['description'] }}</p>
    @endif
</div>

<div class="service-actions">
    @if ($service['public_url'] ?? null)
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

    <span class="state {{ $service['severity']['class'] }}">
        {{ $service['severity']['label'] }}
    </span>
</div>
