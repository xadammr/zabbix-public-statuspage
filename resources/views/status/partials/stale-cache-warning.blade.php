<section class="stale-cache-warning" role="alert">
    <h2>No new data available.</h2>
    <p class="muted">
        The status page is currently showing stale data from
        {{ $cache['refreshed_at']->diffForHumans() }} at {{ $cache['refreshed_at']->format('H:i:s') }}.
    </p>
    <p>
        This could be because of a failure in the monitoring system, or a catastrophic problem with the network or underlying infrastructure.
    </p>
</section>
