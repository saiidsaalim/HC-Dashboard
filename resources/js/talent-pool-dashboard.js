const withOpacity = (hexColor, opacity) => {
    const normalized = hexColor.replace('#', '');

    if (!/^[0-9a-f]{6}$/i.test(normalized)) {
        return hexColor;
    }

    return `#${normalized}${Math.round(opacity * 255).toString(16).padStart(2, '0')}`;
};

export async function initializeTalentPoolDashboard() {
    const dashboards = [...document.querySelectorAll('[data-talent-pool-dashboard]')];

    if (dashboards.length === 0) {
        return;
    }

    const ApexCharts = (await import('apexcharts')).default;

    dashboards.forEach((dashboard) => {
        if (!(dashboard instanceof HTMLElement) || dashboard.dataset.chartInitialized === 'true') {
            return;
        }

        const chartElement = dashboard.querySelector('[data-talent-distribution-chart]');

        if (!(chartElement instanceof HTMLElement)) {
            return;
        }

        dashboard.dataset.chartInitialized = 'true';
        let chart = null;
        let data;

        try {
            data = JSON.parse(chartElement.dataset.chart ?? '{}');
        } catch {
            data = {};
        }

        const renderChart = async () => {
            if (chart !== null) {
                chart.destroy();
                chart = null;
            }

            const isDark = document.documentElement.classList.contains('dark');
            const keys = Array.isArray(data.keys) ? data.keys : [];
            const activeCategory = data.active ?? 'all';
            const colors = (Array.isArray(data.colors) ? data.colors : []).map((color, index) => (
                activeCategory === 'all' || keys[index] === activeCategory ? color : withOpacity(color, 0.24)
            ));

            chart = new ApexCharts(chartElement, {
                chart: {
                    type: 'donut',
                    height: 250,
                    fontFamily: 'inherit',
                    parentHeightOffset: 0,
                    toolbar: { show: false },
                    animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches },
                },
                series: Array.isArray(data.series) ? data.series.map(Number) : [],
                labels: Array.isArray(data.labels) ? data.labels : [],
                colors,
                stroke: { width: 3, colors: [isDark ? '#111c2f' : '#ffffff'] },
                dataLabels: { enabled: false },
                legend: { show: false },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    y: { formatter: (value) => `${Number(value).toLocaleString('id-ID')} pegawai` },
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    offsetY: 18,
                                    color: isDark ? '#94a3b8' : '#64748b',
                                    formatter: () => 'Total Pegawai',
                                },
                                value: { show: false },
                                total: {
                                    show: true,
                                    showAlways: true,
                                    label: '',
                                    color: isDark ? '#f8fafc' : '#0f172a',
                                    fontSize: '24px',
                                    fontWeight: 800,
                                    formatter: () => Number(data.total ?? 0).toLocaleString('id-ID'),
                                },
                            },
                        },
                    },
                },
                noData: {
                    text: 'Data belum tersedia',
                    style: { color: isDark ? '#94a3b8' : '#64748b' },
                },
            });

            await chart.render();
        };

        const synchronizeTheme = () => {
            void renderChart();
        };
        const cleanup = () => {
            window.removeEventListener('themechange', synchronizeTheme);
            chart?.destroy();
            chart = null;
        };

        window.addEventListener('themechange', synchronizeTheme);
        window.addEventListener('pagehide', cleanup, { once: true });
        void renderChart();
    });
}
