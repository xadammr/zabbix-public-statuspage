@php
    $activeTriggers = collect($service['triggers'])->where('value', '1');
@endphp

<details
    class="card service-details"
    @if ($activeTriggers->isNotEmpty()) data-has-active-triggers @endif
>
    <summary class="card-header">
        @include('status.partials.service-card-header', ['service' => $service])
    </summary>

    <div class="service-details-body" data-details-body>
        <div>
            @include('status.partials.service-card-body', [
                'activeTriggers' => $activeTriggers,
                'service' => $service,
            ])
        </div>
    </div>
</details>
