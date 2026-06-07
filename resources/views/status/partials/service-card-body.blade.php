@if ($service['latency'] || $service['api_health'])
    <div class="metrics">
        @if ($service['latency'])
            @include('status.partials.service-latency', ['latency' => $service['latency']])
        @endif

        @if ($service['api_health'])
            @include('status.partials.api-health', ['apiHealth' => $service['api_health']])
        @endif
    </div>
@endif

@if ($activeTriggers->isNotEmpty())
    @include('status.partials.active-triggers', ['triggers' => $activeTriggers])
@endif

@if (count($service['public_metrics']) > 0)
    @include('status.partials.selected-metrics', ['metrics' => $service['public_metrics']])
@endif

@if (config('app.env') !== 'production' && count($service['available_items']) > 0)
    @include('status.partials.available-items', ['items' => $service['available_items']])
@endif
