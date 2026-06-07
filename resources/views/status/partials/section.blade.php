<section class="section" data-section-key="{{ $section['key'] }}">
    <div class="section-header">
        <div>
            <h2>{{ $section['title'] }}</h2>
        </div>

        <button class="section-toggle" type="button" data-section-toggle="{{ $section['key'] }}">Show all</button>
    </div>

    <div class="grid">
        @foreach ($section['services'] as $service)
            @include('status.partials.service-card', [
                'section' => $section,
                'service' => $service,
            ])
        @endforeach
    </div>
</section>
