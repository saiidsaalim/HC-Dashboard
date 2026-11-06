const parseChartData = (element) => {
    try {
        return JSON.parse(element.dataset.chart ?? '{}');
    } catch {
        return {};
    }
};

const hasChartData = (series) => Array.isArray(series) && series.some((value) => Number(value) > 0);

const renderEmptyState = (element) => {
    element.innerHTML = '<div data-critical-position-chart-empty class="flex min-h-[230px] items-center justify-center rounded-xl bg-slate-50 px-4 text-center text-sm text-slate-500">Belum tersedia data untuk ditampilkan.</div>';
};

export async function initializeCriticalPositionDashboard() {
    const dashboard = document.querySelector('[data-critical-position-dashboard]');

    if (!(dashboard instanceof HTMLElement) || dashboard.dataset.initialized === 'true') {
        return;
    }

    dashboard.dataset.initialized = 'true';
    const chartElements = [
        dashboard.querySelector('[data-criticality-chart]'),
        dashboard.querySelector('[data-coverage-chart]'),
        dashboard.querySelector('[data-successor-chart]'),
    ].filter((element) => element instanceof HTMLElement);

    if (chartElements.length === 0) {
        return;
    }

    const populatedChartElements = chartElements.filter((element) => {
        const data = parseChartData(element);

        if (hasChartData(data.series)) {
            return true;
        }

        renderEmptyState(element);

        return false;
    });

    if (populatedChartElements.length === 0) {
        return;
    }

    const ApexCharts = (await import('apexcharts')).default;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const chartInstances = [];
    const chartColors = () => {
        const isDark = document.documentElement.classList.contains('dark');

        return {
            grid: isDark ? '#334155' : '#e2e8f0',
            text: isDark ? '#cbd5e1' : '#475569',
            surface: isDark ? '#0f172a' : '#ffffff',
            tooltip: isDark ? 'dark' : 'light',
        };
    };
    const initialColors = chartColors();
    const baseOptions = {
        chart: {
            height: 245,
            fontFamily: 'inherit',
            parentHeightOffset: 0,
            toolbar: { show: false },
            animations: { enabled: !reduceMotion },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: initialColors.grid, strokeDashArray: 4 },
        legend: {
            fontSize: '12px',
            labels: { colors: initialColors.text },
            markers: { size: 5 },
        },
        tooltip: { theme: initialColors.tooltip },
    };
    const donutChartInstances = [];

    const createDonutChart = (element, colors) => {
        const data = parseChartData(element);
        const chart = new ApexCharts(element, {
            ...baseOptions,
            chart: { ...baseOptions.chart, type: 'donut' },
            series: data.series.map(Number),
            labels: data.categories ?? [],
            colors,
            stroke: { colors: [initialColors.surface], width: 3 },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            name: { show: true, offsetY: 18 },
                            value: { show: false },
                            total: {
                                show: true,
                                label: 'Posisi',
                                formatter: () => new Intl.NumberFormat('id-ID').format(Number(data.total ?? 0)),
                            },
                        },
                    },
                },
            },
            legend: {
                ...baseOptions.legend,
                position: 'right',
                horizontalAlign: 'center',
            },
            responsive: [{
                breakpoint: 1280,
                options: { legend: { position: 'bottom' } },
            }],
        });

        chartInstances.push(chart);
        donutChartInstances.push(chart);
        void chart.render();
    };

    const criticalityElement = dashboard.querySelector('[data-criticality-chart]');
    if (criticalityElement instanceof HTMLElement && hasChartData(parseChartData(criticalityElement).series)) {
        createDonutChart(criticalityElement, ['#ef4444', '#f59e0b', '#86efac']);
    }

    const coverageElement = dashboard.querySelector('[data-coverage-chart]');
    if (coverageElement instanceof HTMLElement && hasChartData(parseChartData(coverageElement).series)) {
        const data = parseChartData(coverageElement);
        const chart = new ApexCharts(coverageElement, {
            ...baseOptions,
            chart: { ...baseOptions.chart, type: 'bar' },
            series: [{ name: 'Position', data: data.series.map(Number) }],
            colors: ['#22c55e', '#38bdf8', '#ef4444', '#a855f7'],
            plotOptions: {
                bar: {
                    borderRadius: 5,
                    distributed: true,
                    horizontal: true,
                    barHeight: '44%',
                    dataLabels: { position: 'top' },
                },
            },
            dataLabels: {
                enabled: true,
                offsetX: 8,
                style: { colors: [initialColors.text], fontSize: '12px' },
            },
            xaxis: {
                categories: data.categories ?? [],
                min: 0,
                tickAmount: Math.min(5, Math.max(1, ...data.series.map(Number))),
                labels: {
                    formatter: (value) => Number.isInteger(Number(value)) ? String(value) : '',
                    style: { colors: initialColors.text, fontSize: '11px' },
                },
            },
            yaxis: { labels: { style: { colors: initialColors.text, fontSize: '11px' } } },
            legend: { show: false },
        });

        chartInstances.push(chart);
        void chart.render();
    }

    const successorElement = dashboard.querySelector('[data-successor-chart]');
    if (successorElement instanceof HTMLElement && hasChartData(parseChartData(successorElement).series)) {
        createDonutChart(successorElement, ['#8b5cf6', '#22c55e']);
    }

    const synchronizeChartTheme = () => {
        const colors = chartColors();

        chartInstances.forEach((chart) => {
            void chart.updateOptions({
                chart: { foreColor: colors.text },
                grid: { borderColor: colors.grid },
                legend: { labels: { colors: colors.text } },
                tooltip: { theme: colors.tooltip },
            }, false, false);
        });
        donutChartInstances.forEach((chart) => {
            void chart.updateOptions({
                stroke: { colors: [colors.surface] },
            }, false, false);
        });
    };

    window.addEventListener('themechange', synchronizeChartTheme);
    window.addEventListener('pagehide', () => {
        window.removeEventListener('themechange', synchronizeChartTheme);
        chartInstances.forEach((chart) => chart.destroy());
    }, { once: true });
}
