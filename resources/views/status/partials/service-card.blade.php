@php
    $activeTriggers = collect($service['triggers'])->where('value', '1');
@endphp

<details class="card service-details">
    <summary class="card-header">
        @include('status.partials.service-card-header', ['service' => $service])
    </summary>

    @include('status.partials.service-card-body', [
        'activeTriggers' => $activeTriggers,
        'service' => $service,
    ])
</details>
