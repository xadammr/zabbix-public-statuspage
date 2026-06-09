<div class="selected-metrics">
    @foreach ($metrics as $metric)
        <div class="selected-metric">
            <span>{{ $metric['name'] }}</span>
            <strong>
                @if ($metric['lastvalue'] !== '')
                    @if (($metric['change']['direction'] ?? 'same') !== 'same')
                        <span
                            class="metric-change {{ $metric['change']['direction'] }}"
                            aria-label="{{ $metric['change']['direction'] === 'up' ? 'Increased' : 'Decreased' }}"
                            title="{{ $metric['change']['direction'] === 'up' ? 'Increased' : 'Decreased' }}"
                        >@if ($metric['change']['direction'] === 'up')&uarr;@else&darr;@endif</span>
                    @endif
                    {{ $metric['display_value'] }}{{ $metric['units'] }}
                @else
                    No value
                @endif
            </strong>
        </div>
    @endforeach
</div>
