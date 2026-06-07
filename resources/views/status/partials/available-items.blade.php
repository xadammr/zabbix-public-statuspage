<details class="items">
    <summary>Available items: {{ count($items) }}</summary>
    <table class="item-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Key</th>
                <th>Latest</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td><code>{{ $item['key'] }}</code></td>
                    <td class="item-value">
                        @if ($item['lastvalue'] !== '')
                            {{ $item['display_value'] }}{{ $item['units'] }}
                        @else
                            <span class="muted">No value</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</details>
