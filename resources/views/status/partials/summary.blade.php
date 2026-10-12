<section class="summary {{ $summary['highest']['class'] }}">
    <h2>
        @if ($wallboard ?? false)
            @if ($summary['problem'] > 0)
                <span class="summary-attention-label">{{ $summary['problem'] }} Active {{ \Illuminate\Support\Str::plural('Alert', $summary['problem']) }}</span>
            @else
                <span class="summary-all-normal">
                    @if (config('statuspage.wallboard_icon_url'))
                        <img class="summary-status-logo" src="{{ config('statuspage.wallboard_icon_url') }}" alt="">
                    @else
                        <span class="summary-status-dot" aria-hidden="true"></span>
                    @endif
                    Everything is OK.
                </span>
            @endif
        @else
            {{ $summary['problem'] > 0
                ? 'Alert Level: '.$summary['highest']['label']
                : 'Everything is OK.' }}
        @endif
    </h2>
    <div class="badges">
        <!--div class="badge monitored">
            <span class="count">{{ $summary['total'] }}</span>
            <span class="label">Monitored</span>
        </div-->
        @foreach ($summary['severity_counts'] as $severity)
            @if ($severity['class'] !== 'ok')
                <div class="badge {{ $severity['class'] }}">
                    <span class="count">{{ $severity['count'] }}</span>
                    <span class="label">{{ $severity['label'] }} </span>
                </div>
            @endif
        @endforeach
        @unless ($forceDisaster ?? false)
            <div class="badge normal">
                @if (($wallboard ?? false) && $summary['problem'] === 0)
                    <span class="count">{{ $summary['total'] }}</span>
                    <span class="label">{{ \Illuminate\Support\Str::plural('Host', $summary['total']) }} Monitored</span>
                @else
                    <span class="count">{{ $summary['ok'] }}</span>
                    <span class="label">Normal</span>
                @endif
            </div>
        @endunless
    </div>
</section>
