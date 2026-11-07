<section class="hc-od-slot hc-od-slot-{{ $slot['type'] }}" data-dashboard-slot="{{ $slotId }}" data-slot-type="{{ $slot['type'] }}" data-slot-widget="{{ $dashboardLayout[$slotId] ?? '' }}" data-empty="{{ $dashboardLayout[$slotId] === null ? 'true' : 'false' }}" :data-target-selected="(choosingSlot === '{{ $slotId }}').toString()" aria-label="Slot {{ $slot['label'] }}">
    <span class="hc-od-slot-label">{{ $slot['label'] }}</span>
    <div data-dashboard-slot-items>
        @if ($dashboardLayout[$slotId] !== null && $widgetCatalog->has($dashboardLayout[$slotId]))
            @include('pages._hc-od-widget', ['widget' => $widgetCatalog->get($dashboardLayout[$slotId])])
        @endif
    </div>
    <div class="hc-od-slot-empty"><span aria-hidden="true">+</span><span>Slot {{ $slot['type'] === 'compact' ? 'ringkas' : 'utama' }} kosong</span><button type="button" class="hc-od-link" @click="openGallery()">Pilih dari galeri</button></div>
</section>
