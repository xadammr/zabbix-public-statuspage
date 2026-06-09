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
            preserveAspectRatio="xMidYMid meet"
        >
            <line class="axis" x1="0" y1="{{ $latency['series']['height'] - 6 }}" x2="{{ $latency['series']['width'] }}" y2="{{ $latency['series']['height'] - 6 }}" />
            @foreach ($latency['series']['thresholds'] as $threshold)
                <line class="threshold" x1="0" y1="{{ $threshold['y'] }}" x2="{{ $latency['series']['width'] }}" y2="{{ $threshold['y'] }}" />
                <text class="threshold-label" x="6" y="{{ max(10, $threshold['y'] - 3) }}">{{ $threshold['value'] }} ms</text>
            @endforeach
            @if (isset($latency['series']['segments']))
                @foreach ($latency['series']['segments'] as $segment)
                    <polyline class="line {{ $segment['class'] }}" points="{{ $segment['points'] }}" />
                @endforeach
            @elseif (isset($latency['series']['points']))
                <polyline class="line ok" points="{{ $latency['series']['points'] }}" />
            @endif
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
