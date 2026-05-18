/**
 * Alpine.js Store Definitions
 * Shared between app and guest layouts to avoid duplication.
 */

document.addEventListener('alpine:init', () => {
    if (!Alpine.store('layout')) {
        Alpine.store('layout', {
            mode: localStorage.getItem('layoutMode') || 'sidebar',
            toggleMode() {
                this.mode = this.mode === 'sidebar' ? 'navbar' : 'sidebar';
                localStorage.setItem('layoutMode', this.mode);
            },
            isSidebar() { return this.mode === 'sidebar'; },
            isNavbar() { return this.mode === 'navbar'; },
        });
    }

    if (!Alpine.store('sidebar')) {
        Alpine.store('sidebar', {
            open: false,
            collapsed: localStorage.getItem('sidebarCollapsed') === 'true',
            toggle() { this.open = !this.open; },
            close() { this.open = false; },
            toggleCollapse() {
                this.collapsed = !this.collapsed;
                localStorage.setItem('sidebarCollapsed', this.collapsed);
            },
        });
    }

    // Kurva S Chart component for global use
    Alpine.data('kurvaSChart', (chartData = null, canvasId = null) => {
        let chartInstance = null; // Plain closure variable — NOT Alpine-reactive, prevents Proxy recursion

        return {
            chartData: chartData,
            canvasId: canvasId,

            isDark() {
                return document.documentElement.classList.contains('dark');
            },

            getColors() {
                const dark = this.isDark();
                return {
                    gridColor  : dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)',
                    tickColor  : dark ? '#9ca3af' : '#6b7280',
                    rencana    : { border: '#3b82f6', bg: 'rgba(59,130,246,0.08)' },
                    aktual     : { border: '#10b981', bg: 'rgba(16,185,129,0.08)' },
                };
            },

            buildDatasets(c, rawData) {
                const src = (rawData !== undefined) ? rawData : this.chartData;
                if (!src || !src.rencana) return [];
                return [
                    {
                        label          : 'Rencana (%)',
                        data           : [...(src.rencana || [])],
                        borderColor    : c.rencana.border,
                        backgroundColor: c.rencana.bg,
                        borderWidth    : 2.5,
                        pointRadius    : 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: c.rencana.border,
                        fill           : true,
                        tension        : 0.35,
                    },
                    {
                        label          : 'Aktual (%)',
                        data           : [...(src.aktual || [])],
                        borderColor    : c.aktual.border,
                        backgroundColor: c.aktual.bg,
                        borderWidth    : 2.5,
                        pointRadius    : 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: c.aktual.border,
                        fill           : true,
                        tension        : 0.35,
                        spanGaps       : false,
                    },
                ];
            },

            init() {
                if (!this.chartData || !this.chartData.labels || !this.chartData.has_rencana) return;
                this.$nextTick(() => {
                    const ctx = this.canvasId ? document.getElementById(this.canvasId) : this.$el.querySelector('canvas');
                    if (!ctx || typeof Chart === 'undefined') return;

                    const existingChart = Chart.getChart(ctx);
                    if (existingChart) existingChart.destroy();
                    if (chartInstance) { chartInstance.destroy(); chartInstance = null; }

                    const c = this.getColors();

                    chartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels  : [...(this.chartData.labels || [])],
                            datasets: this.buildDatasets(c),
                        },
                        options: {
                            responsive        : true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: this.isDark() ? '#1f2937' : '#ffffff',
                                    titleColor      : this.isDark() ? '#f9fafb' : '#111827',
                                    bodyColor       : this.isDark() ? '#d1d5db' : '#374151',
                                    borderColor     : this.isDark() ? '#374151' : '#e5e7eb',
                                    borderWidth     : 1,
                                    padding         : 10,
                                    callbacks: {
                                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y !== null ? ctx.parsed.y.toFixed(2) + '%' : 'N/A'}`,
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid : { color: c.gridColor },
                                    ticks: { color: c.tickColor, maxRotation: 45, font: { size: 11 } },
                                },
                                y: {
                                    min  : 0,
                                    max  : 100,
                                    grid : { color: c.gridColor },
                                    ticks: {
                                        color   : c.tickColor,
                                        font    : { size: 11 },
                                        callback: (v) => v + '%',
                                    },
                                }
                            }
                        }
                    });
                });
            },

            updateData(newChartData) {
                if (!newChartData || !newChartData.labels) return;
                if (!chartInstance) {
                    this.chartData = newChartData;
                    this.init();
                    return;
                }
                const c = this.getColors();
                chartInstance.data.labels   = [...(newChartData.labels || [])];
                chartInstance.data.datasets = this.buildDatasets(c, newChartData);
                chartInstance.update('none');
                this.chartData = newChartData;
            },

            updateColors() {
                if (!chartInstance) return;
                const c = this.getColors();
                chartInstance.data.datasets = this.buildDatasets(c);
                chartInstance.options.scales.x.grid.color  = c.gridColor;
                chartInstance.options.scales.x.ticks.color = c.tickColor;
                chartInstance.options.scales.y.grid.color  = c.gridColor;
                chartInstance.options.scales.y.ticks.color = c.tickColor;
                chartInstance.options.plugins.tooltip.backgroundColor = this.isDark() ? '#1f2937' : '#ffffff';
                chartInstance.options.plugins.tooltip.titleColor      = this.isDark() ? '#f9fafb' : '#111827';
                chartInstance.options.plugins.tooltip.bodyColor       = this.isDark() ? '#d1d5db' : '#374151';
                chartInstance.options.plugins.tooltip.borderColor     = this.isDark() ? '#374151' : '#e5e7eb';
                chartInstance.update();
            },

            destroy() {
                if (chartInstance) {
                    chartInstance.destroy();
                    chartInstance = null;
                }
            },
        };
    });
});

/**
 * Dark mode sync — keeps <html> class in sync with localStorage.
 */
function syncDarkMode() {
    const dark = localStorage.getItem('darkMode') === 'true';
    document.documentElement.classList.toggle('dark', dark);
}

syncDarkMode();
document.addEventListener('livewire:navigated', syncDarkMode);
window.addEventListener('storage', function (e) {
    if (e.key === 'darkMode') syncDarkMode();
});

/**
 * Cleanup orphaned x-teleport elements on Livewire SPA navigation.
 * Prevents stuck tooltips/flyouts and layout glitches after wire:navigate.
 */
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('body > [x-teleport-target]').forEach(el => el.remove());
    document.querySelectorAll('body > .fixed').forEach(el => {
        if (!el.closest('[wire\\:id]') && !el.closest('.min-h-screen') && !el.matches('[x-data]')) {
            el.remove();
        }
    });
});

/**
 * After Livewire SPA navigation completes, dispatch event to reset Alpine component states.
 */
document.addEventListener('livewire:navigated', () => {
    window.dispatchEvent(new Event('resize'));
});
