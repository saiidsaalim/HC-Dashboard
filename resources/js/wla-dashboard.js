const parseChartData = (element) => {
    try {
        return JSON.parse(element.dataset.chart ?? '{}');
    } catch {
        return {};
    }
};

const numberFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 1,
});

export async function initializeWlaDashboard() {
    const dashboard = document.querySelector('[data-wla-dashboard]');

    if (!(dashboard instanceof HTMLElement) || dashboard.dataset.initialized === 'true') {
        return;
    }

    dashboard.dataset.initialized = 'true';
    const chartElements = [
        dashboard.querySelector('[data-wla-distribution-chart]'),
        dashboard.querySelector('[data-wla-trend-chart]'),
        dashboard.querySelector('[data-wla-comparison-chart]'),
    ].filter((element) => element instanceof HTMLElement);

    if (chartElements.length === 0) {
        return;
    }

    const ApexCharts = (await import('apexcharts')).default;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const chartInstances = [];
    const baseOptions = {
        chart: {
            height: 300,
            fontFamily: 'inherit',
            parentHeightOffset: 0,
            toolbar: { show: false },
            animations: { enabled: !reduceMotion },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
        legend: {
            fontSize: '12px',
            labels: { colors: '#475569' },
            markers: { size: 5 },
        },
        noData: {
            text: 'Belum tersedia data untuk ditampilkan',
            style: { color: '#64748b', fontSize: '13px' },
        },
        tooltip: { theme: 'light' },
    };

    const distributionElement = dashboard.querySelector('[data-wla-distribution-chart]');
    if (distributionElement instanceof HTMLElement) {
        const data = parseChartData(distributionElement);
        const chart = new ApexCharts(distributionElement, {
            ...baseOptions,
            chart: { ...baseOptions.chart, type: 'donut' },
            series: data.series ?? [],
            labels: data.categories ?? [],
            colors: ['#1d4ed8', '#2563eb', '#0ea5e9', '#38bdf8', '#7dd3fc', '#cbd5e1'],
            stroke: { colors: ['#ffffff'], width: 3 },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            name: { show: true, offsetY: 18 },
                            value: {
                                show: true,
                                offsetY: -12,
                                formatter: () => numberFormatter.format(Number(data.total ?? 0)),
                            },
                            total: {
                                show: true,
                                label: 'Jam/Tahun',
                                formatter: () => numberFormatter.format(Number(data.total ?? 0)),
                            },
                        },
                    },
                },
            },
            legend: { ...baseOptions.legend, position: 'bottom' },
            tooltip: {
                y: { formatter: (value) => `${numberFormatter.format(value)} Jam/Tahun` },
            },
        });
        chartInstances.push(chart);
        void chart.render();
    }

    const trendElement = dashboard.querySelector('[data-wla-trend-chart]');
    if (trendElement instanceof HTMLElement) {
        const data = parseChartData(trendElement);
        const chart = new ApexCharts(trendElement, {
            ...baseOptions,
            chart: { ...baseOptions.chart, type: 'area' },
            series: [{ name: 'Beban Kerja', data: data.series ?? [] }],
            colors: ['#2563eb'],
            stroke: { curve: 'smooth', width: 3 },
            fill: {
                type: 'gradient',
                gradient: { opacityFrom: 0.3, opacityTo: 0.04, stops: [0, 95, 100] },
            },
            markers: { size: 4, strokeWidth: 2, hover: { sizeOffset: 3 } },
            xaxis: {
                categories: data.categories ?? [],
                axisBorder: { color: '#cbd5e1' },
                axisTicks: { color: '#cbd5e1' },
                labels: { style: { colors: '#64748b', fontSize: '11px' } },
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: (value) => numberFormatter.format(value),
                    style: { colors: '#64748b', fontSize: '11px' },
                },
            },
            legend: { show: false },
            tooltip: { y: { formatter: (value) => `${numberFormatter.format(value)} Jam/Tahun` } },
        });
        chartInstances.push(chart);
        void chart.render();
    }

    const comparisonElement = dashboard.querySelector('[data-wla-comparison-chart]');
    if (comparisonElement instanceof HTMLElement) {
        const data = parseChartData(comparisonElement);
        const chart = new ApexCharts(comparisonElement, {
            ...baseOptions,
            chart: { ...baseOptions.chart, type: 'bar' },
            series: [
                { name: 'FTE', data: data.fte ?? [] },
                { name: 'Rekomendasi Formasi', data: data.recommended ?? [] },
            ],
            colors: ['#1d4ed8', '#7dd3fc'],
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '58%' },
            },
            xaxis: {
                categories: data.categories ?? [],
                labels: {
                    trim: true,
                    hideOverlappingLabels: true,
                    style: { colors: '#64748b', fontSize: '10px' },
                },
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: (value) => numberFormatter.format(value),
                    style: { colors: '#64748b', fontSize: '11px' },
                },
            },
            legend: { ...baseOptions.legend, position: 'top', horizontalAlign: 'right' },
            tooltip: { y: { formatter: (value) => numberFormatter.format(value) } },
        });
        chartInstances.push(chart);
        void chart.render();
    }

    window.addEventListener('pagehide', () => {
        chartInstances.forEach((chart) => chart.destroy());
    }, { once: true });
}
