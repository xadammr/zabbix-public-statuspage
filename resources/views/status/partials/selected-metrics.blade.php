<div class="selected-metrics">
    @foreach ($metrics as $metric)
        <div class="selected-metric">
            <span>{{ $metric['name'] }}</span>
            <strong>
                @if ($metric['lastvalue'] !== '')
                    {{ $metric['display_value'] }}{{ $metric['units'] }}
                @else
                    No value
                @endif
            </strong>
        </div>
    @endforeach
</div>
