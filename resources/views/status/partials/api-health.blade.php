<div class="metric {{ $apiHealth['ok'] ? 'ok' : 'warning' }}">
    <span class="muted">{{ $apiHealth['name'] }}</span>
    <strong>{{ $apiHealth['display_value'] }}</strong>
</div>
