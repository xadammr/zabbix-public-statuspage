<div class="problems">
    @foreach ($triggers as $trigger)
        <div class="problem-row {{ $trigger['priority_class'] }}">
            <span>{{ $trigger['description'] }}</span>
            <span class="problem-priority">{{ $trigger['priority_label'] }}</span>
        </div>
    @endforeach
</div>
