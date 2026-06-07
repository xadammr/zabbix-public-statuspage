@if ($latency['series'])
    <details class="latency-chart">
        <summary>
            <span>Response time</span>
            <strong>{{ $latency['milliseconds'] }} ms</strong>
        </summary>
        <svg
            viewBox="0 0 {{ $latency['series']['width'] }} {{ $latency['series']['height'] }}"
            role="img"
            aria-label="Response times over the past 60 minutes"
            preserveAspectRatio="none"
        >
            @foreach ($latency['series']['bands'] as $band)
                @if ($band['height'] > 0)
                    <rect class="band {{ $band['class'] }}" x="0" y="{{ $band['y'] }}" width="{{ $latency['series']['width'] }}" height="{{ $band['height'] }}" />
                @endif
            @endforeach
            <line class="axis" x1="0" y1="{{ $latency['series']['height'] - 6 }}" x2="{{ $latency['series']['width'] }}" y2="{{ $latency['series']['height'] - 6 }}" />
            @foreach ($latency['series']['thresholds'] as $threshold)
                <line class="threshold" x1="0" y1="{{ $threshold['y'] }}" x2="{{ $latency['series']['width'] }}" y2="{{ $threshold['y'] }}" />
                <text class="threshold-label" x="6" y="{{ max(10, $threshold['y'] - 3) }}">{{ $threshold['value'] }} ms</text>
            @endforeach
            <polyline class="line" points="{{ $latency['series']['points'] }}" />
        </svg>
        <div class="chart-meta">
            <span>60 min</span>
            <span>
                {{ $latency['series']['min_ms'] }}-{{ $latency['series']['max_ms'] }} ms
                · {{ $latency['series']['samples'] }} samples
            </span>
        </div>
    </details>
@else
    <div class="metric">
        <span class="muted">Response time</span>
        <strong>{{ $latency['milliseconds'] }} ms</strong>
    </div>
@endif
