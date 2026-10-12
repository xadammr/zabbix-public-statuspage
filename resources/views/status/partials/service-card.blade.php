@php
    $displayService = $service;

    if ($forceDisaster ?? false) {
        $displayService['severity'] = [
            'class' => 'disaster',
            'label' => 'Disaster',
        ];
    }

    $activeTriggers = collect($service['triggers'])->where('value', '1');
@endphp

<details
    class="card service-details severity-{{ $displayService['severity']['class'] }}"
    data-service-id="{{ $service['hostid'] }}"
    @if ($activeTriggers->isNotEmpty()) data-has-active-triggers @endif
>
    <summary class="card-header">
        @include('status.partials.service-card-header', [
            'hideLink' => $wallboard ?? false,
            'hideState' => $wallboardAlert ?? false,
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

    <div class="service-details-body" data-details-body>
        <div class="service-details-content">
            @include('status.partials.service-card-body', [
                'activeTriggers' => ($wallboardAlert ?? false) ? collect() : $activeTriggers,
                'service' => $service,
            ])
        </div>
    </div>
</details>
