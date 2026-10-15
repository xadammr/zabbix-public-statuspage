<div class="problems">
    @foreach ($triggers as $trigger)
        @php
            $description = $trigger['description'];
            $delimiterPosition = strpos($description, ':');
        @endphp
        <div class="problem-row {{ $trigger['priority_class'] }}">
            <span>
                @if ($delimiterPosition !== false)
                    <strong>{{ substr($description, 0, $delimiterPosition + 1) }}</strong>{{ substr($description, $delimiterPosition + 1) }}
                @else
                    {{ $description }}
                @endif
            </span>
            <span class="problem-priority">{{ $trigger['priority_label'] }}</span>
        </div>
    @endforeach
</div>
