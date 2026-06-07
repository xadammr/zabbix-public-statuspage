<div>
    <h3>{{ $service['name'] }}</h3>
    @if ($service['description'])
        <p class="muted">{{ $service['description'] }}</p>
    @endif
</div>

<span class="state {{ $service['severity']['class'] }}">
    {{ $service['severity']['label'] }}
</span>
