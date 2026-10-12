<section class="section" data-section-key="{{ $section['key'] }}">
    <div class="section-header">
        <div>
            <h2>{{ $section['title'] }}</h2>
        </div>

        @unless ($wallboard ?? false)
            <button class="section-toggle" type="button" data-section-toggle="{{ $section['key'] }}">Show all</button>
        @endunless
    </div>

    <div class="grid">
        @foreach ($section['services'] as $service)
            @include('status.partials.service-card', [
                'forceDisaster' => $forceDisaster ?? false,
                'section' => $section,
                'service' => $service,
                'wallboard' => $wallboard ?? false,
            ])
        @endforeach
    </div>
</section>
