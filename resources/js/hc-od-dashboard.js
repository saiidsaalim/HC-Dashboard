const slotTypes = { 'compact-1': 'compact', 'compact-2': 'compact', 'main-1': 'main', 'main-2': 'main', 'right-1': 'main', 'right-2': 'main' };
const slotLabels = { 'compact-1': 'Kiri · Slot 1', 'compact-2': 'Kiri · Slot 2', 'main-1': 'Tengah · Slot 1', 'main-2': 'Tengah · Slot 2', 'right-1': 'Kanan · Slot 1', 'right-2': 'Kanan · Slot 2' };
const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const copySlots = slots => Object.fromEntries(Object.keys(slotTypes).map(slot => [slot, slots[slot] ?? null]));

export function registerHcOdDashboard(Alpine) {
    Alpine.data('hcOdDashboard', () => {
        let root;
        let calendar;
        let sortableInstances = [];
        let Sortable;
        let calendarController;
        let resizeObserver;
        return {
            editing: false, saving: false, galleryOpen: false, choosingId: null, choosingSlot: null,
            error: '', notice: '', widgets: [], saved: {}, draft: {}, config: {},
            calendarLoading: true, calendarError: '', calendarEvents: [], canViewCalendar: false,
            selectedDate: dateKey(new Date()),
            get choosingType() { return this.widgets.find(widget => widget.id === this.choosingId)?.type ?? ''; },
            get choosingTitle() { return this.widgets.find(widget => widget.id === this.choosingId)?.title ?? ''; },
            get hasMainWidgets() { return Object.entries(slotTypes).some(([slot, type]) => type === 'main' && this.draft[slot]); },
            get replacementText() {
                if (!this.choosingSlot) return 'Pilih slot tujuan terlebih dahulu.';
                const widget = this.widgets.find(item => item.id === this.draft[this.choosingSlot]);
                return widget ? `Mengganti: ${widget.title}` : `Tambahkan ke ${slotLabels[this.choosingSlot]}.`;
            },
            get compatibleSlots() {
                const slots = Object.entries(slotTypes).filter(([, type]) => type === this.choosingType).map(([id]) => {
                    const widget = this.widgets.find(item => item.id === this.draft[id]);
                    return { id, label: slotLabels[id], widgetTitle: widget?.title ?? 'Slot kosong', current: this.draft[id] === this.choosingId };
                });
                const focusSlot = this.choosingSlot ?? slots.find(slot => !slot.current)?.id;
                return slots.map(slot => ({ ...slot, tabIndex: slot.id === focusSlot ? 0 : -1 }));
            },
            get selectedEvents() { return this.calendarEvents.filter(event => event.start.slice(0, 10) === this.selectedDate); },
            get selectedDateLabel() { return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${this.selectedDate}T12:00:00`)); },
            init() {
                root = this.$el;
                this.config = JSON.parse(root.querySelector('[data-dashboard-config]').textContent);
                this.widgets = this.config.widgets;
                this.saved = copySlots(this.config.slots);
                this.draft = copySlots(this.saved);
                this.canViewCalendar = this.config.canViewCalendar;
                root.addEventListener('click', event => {
                    const move = event.target.closest('[data-widget-move]');
                    const remove = event.target.closest('[data-widget-remove]');
                    if (move) this.chooseWidget(move.dataset.widgetMove);
                    if (remove) this.removeWidget(remove.dataset.widgetRemove);
                    if (this.editing && event.target.closest('[data-dashboard-widget] a')) event.preventDefault();
                });
                this.$watch('editing', () => this.$nextTick(() => { this.syncSortables(); calendar?.updateSize(); }));
                this.$watch('galleryOpen', () => this.$nextTick(() => { calendar?.updateSize(); this.resizeCharts(); }));
                this.$nextTick(() => this.resizeCharts());
                this.$nextTick(() => this.initializeCalendar());
            },
            isInstalled(id) { return Object.values(this.draft).includes(id); },
            async startEditing() {
                this.draft = copySlots(this.saved);
                this.error = ''; this.notice = ''; this.editing = true; this.galleryOpen = true;
                try {
                    if (!Sortable) Sortable = (await import('sortablejs')).default;
                    if (this.editing) this.initializeSortables();
                } catch {
                    this.error = 'Seret belum tersedia. Gunakan tombol + atau Pindahkan untuk mengatur widget.';
                }
            },
            cancelEditing() {
                if (this.saving) return;
                this.draft = copySlots(this.saved);
                this.closeGallery(false); this.error = ''; this.notice = '';
                this.editing = false; this.renderSlots();
                this.$nextTick(() => root.querySelector('[data-dashboard-edit]')?.focus());
            },
            chooseWidget(id) {
                if (!this.editing || this.saving || !this.widgets.some(widget => widget.id === id)) return;
                this.galleryOpen = true; this.choosingId = id; this.choosingSlot = null;
                this.$nextTick(() => {
                    root.querySelector('[data-dashboard-slot-picker]')?.scrollIntoView({ block: 'nearest', behavior: 'instant' });
                    root.querySelector('[data-dashboard-place]:not([disabled])')?.focus({ preventScroll: true });
                });
            },
            openGallery() {
                if (!this.editing || this.saving) return;
                this.galleryOpen = true;
                this.$nextTick(() => root.querySelector('.hc-od-gallery-close')?.focus({ preventScroll: true }));
            },
            closeGallery(restoreFocus = true) {
                this.galleryOpen = false; this.clearChoice();
                if (restoreFocus && this.editing) this.$nextTick(() => root.querySelector('[data-dashboard-gallery-toggle]')?.focus({ preventScroll: true }));
            },
            clearChoice() { this.choosingId = null; this.choosingSlot = null; },
            selectSlot(slot) {
                if (this.saving || !this.compatibleSlots.some(item => item.id === slot && !item.current)) return;
                this.choosingSlot = slot;
            },
            moveSlotSelection(step) {
                const slots = this.compatibleSlots.filter(slot => !slot.current);
                if (!slots.length) return;
                const current = slots.findIndex(slot => slot.id === this.choosingSlot);
                const next = slots[current < 0 ? (step > 0 ? 0 : slots.length - 1) : (current + step + slots.length) % slots.length];
                this.selectSlot(next.id);
                this.$nextTick(() => root.querySelector(`[data-dashboard-place="${next.id}"]`)?.focus());
            },
            applyWidget() {
                const id = this.choosingId;
                const slot = this.choosingSlot;
                if (!id || !slot || this.saving) return;
                this.placeWidget(id, slot);
                this.closeGallery(false);
                this.$nextTick(() => root.querySelector(`[data-widget-move="${id}"]`)?.focus({ preventScroll: true }));
            },
            placeWidget(id, slot) {
                const widget = this.widgets.find(item => item.id === id);
                if (!this.editing || this.saving || !widget || slotTypes[slot] !== widget.type) return;
                const previousSlot = Object.keys(slotTypes).find(key => this.draft[key] === id);
                if (previousSlot === slot) return;
                const next = copySlots(this.draft);
                if (previousSlot) next[previousSlot] = next[slot];
                next[slot] = id;
                this.draft = next; this.error = ''; this.renderSlots();
            },
            removeWidget(id) {
                if (!this.editing || this.saving) return;
                this.draft = Object.fromEntries(Object.entries(this.draft).map(([slot, widget]) => [slot, widget === id ? null : widget]));
                this.renderSlots();
                this.$nextTick(() => root.querySelector('[data-dashboard-cancel]')?.focus({ preventScroll: true }));
            },
            renderSlots() {
                for (const slot of root.querySelectorAll('[data-dashboard-slot]')) {
                    const id = this.draft[slot.dataset.dashboardSlot];
                    const items = slot.querySelector('[data-dashboard-slot-items]');
                    items.replaceChildren();
                    const template = [...root.querySelectorAll('[data-dashboard-widget-template]')].find(node => node.dataset.dashboardWidgetTemplate === id);
                    if (template) items.append(template.content.cloneNode(true));
                    slot.dataset.empty = id ? 'false' : 'true';
                    slot.dataset.slotWidget = id ?? '';
                }
                calendar?.updateSize();
                requestAnimationFrame(() => this.resizeCharts());
            },
            resizeCharts() {
                for (const chart of root.querySelectorAll('[data-career-chart]')) {
                    const width = Math.round(chart.clientWidth);
                    const height = Math.round(chart.clientHeight);
                    if (width < 100 || height < 80) continue;
                    chart.setAttribute('viewBox', `0 0 ${width} ${height}`);
                    const scaleX = (width - 45) / 575;
                    const scaleY = (height - 56) / 132;
                    for (const node of chart.querySelectorAll('[x], [x1], [x2], [y], [y1], [y2], rect')) {
                        for (const name of ['x', 'x1', 'x2', 'y', 'y1', 'y2', 'width', 'height']) {
                            if (!node.hasAttribute(name)) continue;
                            const key = `data-chart-original-${name}`;
                            if (!node.hasAttribute(key)) node.setAttribute(key, node.getAttribute(name));
                            const value = Number(node.getAttribute(key));
                            const next = name === 'y' && node.classList.contains('hc-od-chart-value') ? 18 + (value + 6 - 18) * scaleY - 6
                                : name === 'width' ? value * scaleX : name === 'height' ? value * scaleY
                                : name.startsWith('x') ? (value < 35 ? value : 35 + (value - 35) * scaleX)
                                    : value > 150 ? height - 38 + (value - 150) : 18 + (value - 18) * scaleY;
                            node.setAttribute(name, String(next));
                        }
                    }
                }
            },
            prepareDrag() {
                for (const slot of root.querySelectorAll('[data-dashboard-slot]')) {
                    slot.style.minHeight = `${slot.getBoundingClientRect().height}px`;
                }
            },
            finishDrag() {
                delete root.dataset.draggingType;
                for (const slot of root.querySelectorAll('[data-dashboard-slot]')) slot.style.removeProperty('min-height');
            },
            initializeSortables() {
                if (!Sortable || sortableInstances.length) return;
                for (const slot of root.querySelectorAll('[data-dashboard-slot]')) {
                    const items = slot.querySelector('[data-dashboard-slot-items]');
                    sortableInstances.push(new Sortable(items, {
                        group: `hc-dashboard-${slot.dataset.slotType}`, animation: 140,
                        handle: '[data-widget-drag]', draggable: '[data-widget-id]',
                        onChoose: () => this.prepareDrag(),
                        onUnchoose: () => { if (!root.dataset.draggingType) this.finishDrag(); },
                        onStart: () => { root.dataset.draggingType = slot.dataset.slotType; },
                        onAdd: event => {
                            const id = event.item.dataset.widgetId;
                            requestAnimationFrame(() => { this.placeWidget(id, slot.dataset.dashboardSlot); this.renderSlots(); this.closeGallery(false); });
                        },
                        onEnd: () => this.finishDrag(),
                    }));
                }
                for (const gallery of root.querySelectorAll('[data-gallery-list]')) {
                    sortableInstances.push(new Sortable(gallery, {
                        group: { name: `hc-dashboard-${gallery.dataset.galleryList}`, pull: 'clone', put: false },
                        sort: false, animation: 140, handle: '[data-widget-drag]', draggable: '[data-widget-id]',
                        filter: '[data-installed="true"]',
                        onChoose: () => this.prepareDrag(),
                        onUnchoose: () => { if (!root.dataset.draggingType) this.finishDrag(); },
                        onStart: () => { root.dataset.draggingType = gallery.dataset.galleryList; },
                        onEnd: () => this.finishDrag(),
                    }));
                }
                this.syncSortables();
                root.dataset.dragReady = 'true';
            },
            syncSortables() { for (const sortable of sortableInstances) sortable.option('disabled', !this.editing || this.saving); },
            async saveLayout() {
                if (!this.editing || this.saving) return;
                this.saving = true; this.error = ''; this.syncSortables();
                try {
                    const response = await fetch(this.config.saveUrl, {
                        method: 'PUT', credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ workspace: this.config.workspace, slots: this.draft }),
                    });
                    const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
                    if (!response.ok || !data?.slots) throw new Error(data?.message || 'Susunan belum tersimpan. Coba lagi; perubahan Anda tetap tersedia.');
                    this.saved = copySlots(data.slots); this.draft = copySlots(this.saved);
                    this.editing = false; this.closeGallery(false); this.renderSlots(); this.notice = 'Susunan dashboard tersimpan.';
                    this.$nextTick(() => root.querySelector('[data-dashboard-edit]')?.focus({ preventScroll: true }));
                } catch (error) {
                    this.error = error instanceof TypeError ? 'Koneksi terputus. Draf tetap tersedia; coba simpan kembali.' : error.message;
                } finally {
                    this.saving = false; this.syncSortables();
                }
            },
            async initializeCalendar() {
                try {
                    const [{ Calendar }, { default: dayGrid }, { default: locale }] = await Promise.all([
                        import('@fullcalendar/core'), import('@fullcalendar/daygrid'), import('@fullcalendar/core/locales/id'),
                    ]);
                    const element = root.querySelector('[data-dashboard-month]');
                    calendar = new Calendar(element, {
                        plugins: [dayGrid], initialView: 'dayGridMonth', locale,
                        headerToolbar: { left: 'title', right: 'prev,next' },
                        height: 'auto', fixedWeekCount: true, eventDisplay: 'none', firstDay: 1,
                        dayCellContent: argument => {
                            const button = document.createElement('button');
                            button.type = 'button'; button.className = 'hc-od-day';
                            button.dataset.calendarDate = dateKey(argument.date);
                            button.textContent = argument.dayNumberText;
                            button.setAttribute('aria-label', new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(argument.date));
                            button.addEventListener('click', () => this.selectDate(button.dataset.calendarDate));
                            return { domNodes: [button] };
                        },
                        datesSet: () => {
                            if (!calendar) return;
                            const month = dateKey(calendar.getDate()).slice(0, 7);
                            if (!this.selectedDate.startsWith(month)) this.selectedDate = `${month}-01`;
                            this.refreshCalendarMarkers();
                        },
                        events: async (info, success, failure) => {
                            this.calendarLoading = true; this.calendarError = ''; this.calendarEvents = [];
                            if (!this.canViewCalendar) { this.calendarEvents = []; this.calendarLoading = false; success([]); return; }
                            calendarController?.abort(); calendarController = new AbortController();
                            const requestController = calendarController;
                            try {
                                const url = new URL(this.config.calendarUrl, location.href);
                                url.searchParams.set('start', info.startStr.slice(0, 10)); url.searchParams.set('end', info.endStr.slice(0, 10));
                                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: requestController.signal });
                                if (!response.ok) throw new Error('Agenda tidak dapat dimuat. Coba lagi.');
                                const events = await response.json();
                                if (requestController !== calendarController) return;
                                this.calendarEvents = events; this.calendarLoading = false; success(events); this.refreshCalendarMarkers();
                            } catch (error) {
                                if (error.name === 'AbortError' || requestController !== calendarController) return;
                                this.calendarEvents = []; this.calendarError = 'Agenda tidak dapat dimuat. Coba lagi.'; this.calendarLoading = false;
                                failure(error);
                            }
                        },
                        eventsSet: () => this.refreshCalendarMarkers(),
                    });
                    calendar.render();
                    let previousWidths = '';
                    const workspace = root.querySelector('.hc-od-workspace');
                    resizeObserver = new ResizeObserver(() => {
                        const widths = `${element.clientWidth}:${workspace.clientWidth}:${workspace.clientHeight}`;
                        if (widths !== previousWidths) {
                            previousWidths = widths;
                            requestAnimationFrame(() => { calendar?.updateSize(); this.resizeCharts(); });
                        }
                    });
                    resizeObserver.observe(element);
                    resizeObserver.observe(workspace);
                    this.refreshCalendarMarkers();
                } catch {
                    this.calendarLoading = false; this.calendarError = 'Kalender tidak dapat dimuat. Coba lagi.';
                }
            },
            selectDate(key) { this.selectedDate = key; this.refreshCalendarMarkers(); },
            refreshCalendarMarkers() {
                const counts = new Map();
                for (const event of this.calendarEvents) { const key = event.start.slice(0, 10); counts.set(key, (counts.get(key) ?? 0) + 1); }
                for (const button of root.querySelectorAll('[data-calendar-date]')) {
                    const count = counts.get(button.dataset.calendarDate) ?? 0;
                    button.classList.toggle('has-events', count > 0);
                    button.closest('td')?.classList.toggle('has-events', count > 0);
                    button.setAttribute('aria-pressed', (button.dataset.calendarDate === this.selectedDate).toString());
                    const label = new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(new Date(`${button.dataset.calendarDate}T12:00:00`));
                    button.setAttribute('aria-label', `${label}${count ? `, ${count} kegiatan` : ''}`);
                }
            },
            retryCalendar() { if (calendar) calendar.refetchEvents(); else this.initializeCalendar(); },
            destroy() { calendarController?.abort(); resizeObserver?.disconnect(); calendar?.destroy(); sortableInstances.forEach(sortable => sortable.destroy()); },
        };
    });
}
