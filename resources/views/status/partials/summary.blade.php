<section class="summary {{ $summary['highest']['class'] }}">
    <h2>
        {{ $summary['problem'] > 0
            ? 'Alert Level: '.$summary['highest']['label']
            : 'Everything is OK.' }}
    </h2>
    <div class="badges">
        <!--div class="badge monitored">
            <span class="count">{{ $summary['total'] }}</span>
            <span class="label">Monitored</span>
        </div-->
        @unless ($forceDisaster ?? false)
            <div class="badge normal">
                <span class="count">{{ $summary['ok'] }}</span>
                <span class="label">Normal</span>
            </div>
        @endunless
        @foreach ($summary['severity_counts'] as $severity)
            @if ($severity['class'] !== 'ok')
                <div class="badge {{ $severity['class'] }}">
                    <span class="count">{{ $severity['count'] }}</span>
                    <span class="label">{{ $severity['label'] }} </span>
                </div>
            @endif
        @endforeach
    </div>
</section>
