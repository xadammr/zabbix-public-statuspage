<section class="summary {{ $summary['highest']['class'] }}">
    <h2>
        @if ($wallboard ?? false)
            @if ($summary['problem'] > 0)
                <span class="summary-severity-pill {{ $summary['highest']['class'] }}">
                    {{ strtoupper($summary['highest']['label']) }}
                </span>
                <span class="summary-attention-label">Services need attention</span>
            @else
                <span class="summary-all-normal">
                    <span class="summary-status-dot" aria-hidden="true"></span>
                    All systems normal
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
                <span class="count">{{ $summary['ok'] }}</span>
                <span class="label">Normal</span>
            </div>
        @endunless
    </div>
</section>
