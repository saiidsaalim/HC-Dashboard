@extends('layouts.app')

@section('page-title', 'Organisasi & Karier')

@section('content')
    @php
        $widgetCatalog = collect($dashboardWidgets)->keyBy('id');
        $dashboardSlots = [
            'compact-1' => ['type' => 'compact', 'label' => 'Kiri · Slot 1'],
            'compact-2' => ['type' => 'compact', 'label' => 'Kiri · Slot 2'],
            'main-1' => ['type' => 'main', 'label' => 'Tengah · Slot 1'],
            'main-2' => ['type' => 'main', 'label' => 'Tengah · Slot 2'],
            'right-1' => ['type' => 'main', 'label' => 'Kanan · Slot 1'],
            'right-2' => ['type' => 'main', 'label' => 'Kanan · Slot 2'],
        ];
        $dashboardConfig = ['workspace' => 'hc-od-career-management', 'slots' => $dashboardLayout, 'widgets' => $dashboardWidgets, 'saveUrl' => route('dashboard.layout'), 'calendarUrl' => route('dashboard.calendar'), 'canViewCalendar' => $canViewCalendar];
    @endphp
    <div class="hc-od-dashboard hc-od-personal" data-hc-od-dashboard data-editing="false" data-gallery-open="false" x-data="hcOdDashboard" :data-editing="editing.toString()" :data-gallery-open="(editing && galleryOpen).toString()" :data-choosing-type="choosingType" @keydown.escape.window="closeGallery()">
        <script type="application/json" data-dashboard-config>@json($dashboardConfig)</script>

        <div class="hc-od-heading">
            <div>
                <div class="hc-od-heading-title"><h1>Organisasi &amp; Karier</h1><span x-cloak x-show="editing" class="hc-od-edit-badge">Mode Edit</span></div>
                <p x-text="editing ? 'Pilih widget dan susun sesuai kebutuhan Anda.' : 'Ringkasan tenaga kerja dan proses pengembangan karier'">Ringkasan tenaga kerja dan proses pengembangan karier</p>
            </div>
            <div class="hc-od-edit-actions">
                <button type="button" class="ui-button ui-button-secondary" data-dashboard-edit x-show="!editing" @click="startEditing()"><x-dashboard-icon name="edit" aria-hidden="true" />Atur Dashboard</button>
                <button x-cloak x-show="editing" type="button" class="ui-button ui-button-secondary hc-od-gallery-toggle" data-dashboard-gallery-toggle @click="openGallery()" :disabled="saving" :aria-expanded="galleryOpen.toString()" aria-controls="dashboard-widget-gallery"><span aria-hidden="true">+</span> Tambah Widget</button>
                <button x-cloak x-show="editing" type="button" class="ui-button ui-button-secondary" data-dashboard-cancel :disabled="saving" @click="cancelEditing()">Batal</button>
                <button x-cloak x-show="editing" type="button" class="ui-button ui-button-primary" data-dashboard-save :disabled="saving" @click="saveLayout()" x-text="saving ? 'Menyimpan…' : 'Simpan Susunan'">Simpan Susunan</button>
            </div>
        </div>
        <p x-cloak x-show="error" x-text="error" class="hc-od-save-error" role="alert" data-dashboard-error></p>
        <p x-cloak x-show="notice" x-text="notice" class="hc-od-save-notice" role="status"></p>

        <div class="hc-od-workbench">
        <div class="hc-od-workspace">
            <div class="hc-od-left-column">
                <section class="hc-od-panel hc-od-calendar" data-dashboard-calendar aria-labelledby="dashboard-calendar-title">
                    <div class="hc-od-panel-heading"><h2 id="dashboard-calendar-title">Kalender Kegiatan</h2><span class="hc-od-calendar-dot" title="Jadwal Fit & Proper" aria-hidden="true"></span></div>
                    <div data-dashboard-month class="hc-od-month"></div>
                    <p x-show="calendarLoading" class="hc-od-calendar-note" role="status">Memuat kalender…</p>
                    <p x-cloak x-show="calendarError" class="hc-od-calendar-note" role="alert" x-text="calendarError"></p>
                    <button x-cloak x-show="calendarError && canViewCalendar" type="button" class="hc-od-link" @click="retryCalendar()">Coba lagi</button>
                    <div class="hc-od-agenda" data-dashboard-agenda>
                        <h3 x-text="selectedDateLabel">Agenda hari ini</h3>
                        <p x-show="!canViewCalendar" class="hc-od-calendar-note">Anda belum memiliki akses agenda Fit &amp; Proper.</p>
                        <p x-cloak x-show="canViewCalendar && !calendarLoading && !calendarError && selectedEvents.length === 0" class="hc-od-calendar-note">Tidak ada kegiatan</p>
                        <template x-for="event in selectedEvents" :key="event.id"><a :href="event.url"><span x-text="event.title"></span><span aria-hidden="true">↗</span></a></template>
                    </div>
                </section>
                @foreach (array_slice($dashboardSlots, 0, 2, true) as $slotId => $slot)
                    @include('pages._hc-od-slot')
                @endforeach
            </div>
            <div class="hc-od-main-column">
                @foreach (array_slice($dashboardSlots, 2, 2, true) as $slotId => $slot)
                    @include('pages._hc-od-slot')
                @endforeach
                <p x-cloak x-show="!editing && !hasMainWidgets" class="hc-od-no-widgets">Tambahkan grafik atau panel melalui Atur Dashboard.</p>
            </div>
            <div class="hc-od-right-column">
                @foreach (array_slice($dashboardSlots, 4, 2, true) as $slotId => $slot)
                    @include('pages._hc-od-slot')
                @endforeach
            </div>
        </div>

            <aside x-cloak x-show="editing && galleryOpen" id="dashboard-widget-gallery" class="hc-od-gallery" data-dashboard-gallery aria-labelledby="dashboard-gallery-title" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-8 opacity-0">
                <div class="hc-od-gallery-heading"><h2 id="dashboard-gallery-title">Tambah Widget</h2><button class="hc-od-gallery-close" type="button" @click="closeGallery()" aria-label="Tutup galeri">×</button></div>
                <p>Seret atau tekan + untuk menambahkan.</p>
                <div class="hc-od-gallery-scroll">
                @foreach (['compact' => 'Widget Ringkas', 'main' => 'Widget Utama'] as $type => $typeLabel)
                    <section class="hc-od-gallery-group" x-show="!choosingId || choosingType === '{{ $type }}'">
                    <h3>{{ $typeLabel }}</h3>
                    <div data-gallery-list="{{ $type }}" class="hc-od-gallery-list">
                        @foreach ($dashboardWidgets as $widget)
                            @continue($widget['type'] !== $type)
                            <div class="hc-od-gallery-item" data-gallery-widget="{{ $widget['id'] }}" data-widget-id="{{ $widget['id'] }}" data-widget-type="{{ $type }}" :data-installed="isInstalled('{{ $widget['id'] }}').toString()" :data-selected="(choosingId === '{{ $widget['id'] }}').toString()">
                                <span class="hc-od-gallery-icon" data-widget-drag aria-hidden="true"><x-dashboard-icon :name="$widget['icon']" /></span>
                                <div><strong>{{ $widget['title'] }}</strong><span x-text="isInstalled('{{ $widget['id'] }}') ? 'Terpasang' : choosingId === '{{ $widget['id'] }}' ? 'Dipilih' : @js($widget['description'])">{{ $widget['description'] }}</span></div>
                                <button type="button" data-widget-add="{{ $widget['id'] }}" @click="chooseWidget('{{ $widget['id'] }}')" :disabled="saving || isInstalled('{{ $widget['id'] }}')" aria-label="Tambahkan {{ $widget['title'] }}">+</button>
                            </div>
                        @endforeach
                    </div>
                    </section>
                @endforeach
                @if ($dashboardWidgets === [])<p>Belum ada modul ringkasan yang dapat ditampilkan.</p>@endif
                <div x-cloak x-show="choosingId !== null" class="hc-od-slot-picker" data-dashboard-slot-picker>
                    <div class="hc-od-picker-heading"><h3>Pilih slot tujuan</h3><button type="button" @click="clearChoice()" class="hc-od-link">Widget lain</button></div>
                    <p x-text="choosingTitle"></p>
                    <div role="radiogroup" aria-label="Slot tujuan widget" @keydown.arrow-down.prevent="moveSlotSelection(1)" @keydown.arrow-right.prevent="moveSlotSelection(1)" @keydown.arrow-up.prevent="moveSlotSelection(-1)" @keydown.arrow-left.prevent="moveSlotSelection(-1)">
                        <template x-for="slot in compatibleSlots" :key="slot.id">
                            <button type="button" role="radio" :aria-checked="(choosingSlot === slot.id).toString()" :tabindex="slot.tabIndex" :data-dashboard-place="slot.id" :disabled="saving || slot.current" @click="selectSlot(slot.id)">
                                <span class="hc-od-slot-radio" aria-hidden="true"></span><span><strong x-text="slot.label"></strong><small x-text="slot.widgetTitle"></small></span>
                            </button>
                        </template>
                    </div>
                </div>
                </div>
                <div class="hc-od-gallery-footer" x-cloak x-show="choosingId !== null">
                    <p x-show="choosingSlot" x-text="replacementText" data-dashboard-replacement></p>
                    <button type="button" class="ui-button ui-button-primary" data-dashboard-apply :disabled="saving || !choosingSlot" @click="applyWidget()" x-text="choosingSlot && draft[choosingSlot] ? 'Ganti Widget' : 'Tambah ke Slot'">Tambah ke Slot</button>
                </div>
            </aside>
        </div>

        @if ($dashboardWidgets === [])<p class="hc-od-calendar-note">Belum ada modul ringkasan yang dapat ditampilkan.</p>@endif

        @foreach ($dashboardWidgets as $widget)
            <template data-dashboard-widget-template="{{ $widget['id'] }}">@include('pages._hc-od-widget')</template>
        @endforeach

    </div>
@endsection
