@php
    $displayService = $service;

    if ($forceDisaster ?? false) {
        $displayService['severity'] = [
            'class' => 'disaster',
            'label' => 'Disaster',
        ];
    }

    $activeTriggers = collect($service['triggers'])->where('value', '1');
    $detailActiveTriggers = ($wallboardAlert ?? false) ? collect() : $activeTriggers;
    $hasExpandableContent = $detailActiveTriggers->isNotEmpty()
        || $service['latency']
        || $service['api_health']
        || count($service['public_metrics']) > 0
        || (config('zabbix.statuspage_fetch_available_items') && count($service['available_items']) > 0);
    $alertStartedAt = $activeTriggers
        ->pluck('lastchange')
        ->filter(fn ($lastChange) => is_numeric($lastChange) && (int) $lastChange > 0)
        ->map(fn ($lastChange) => (int) $lastChange)
        ->min();
@endphp

<details
    class="card service-details severity-{{ $displayService['severity']['class'] }}"
    data-service-id="{{ $service['hostid'] }}"
    data-expandable="{{ $hasExpandableContent ? 'true' : 'false' }}"
    @if ($activeTriggers->isNotEmpty()) data-has-active-triggers @endif
>
    <summary class="card-header" @unless ($hasExpandableContent) aria-disabled="true" @endunless>
        @include('status.partials.service-card-header', [
            'hideLink' => $wallboard ?? false,
            'hideState' => $wallboardAlert ?? false,
            'alertStartedAt' => ($wallboardAlert ?? false) ? $alertStartedAt : null,
            'service' => $displayService,
        ])

        @if ($wallboardAlert ?? false)
            <div class="wallboard-alert-problems" aria-label="Alert reason">
                @if ($activeTriggers->isNotEmpty())
                    @include('status.partials.active-triggers', ['triggers' => $activeTriggers])
                @elseif ($forceDisaster ?? false)
                    <div class="problems">
                        <div class="problem-row disaster">
                            <span>Monitoring data is stale</span>
                            <span class="problem-priority">Disaster</span>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </summary>

    @if ($hasExpandableContent)
        <div class="service-details-body" data-details-body>
            <div class="service-details-content">
                @include('status.partials.service-card-body', [
                    'activeTriggers' => $detailActiveTriggers,
                    'service' => $service,
                ])
            </div>
        </div>
    @endif
</details>
