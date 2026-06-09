/* global Chart, wcdData */
( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        if ( typeof wcdData === 'undefined' ) return;

        const palette = {
            purple     : '#7B5EA7',
            purpleLight: 'rgba(123,94,167,.15)',
            blue       : '#3b82f6',
            blueLight  : 'rgba(59,130,246,.15)',
            green      : '#22c55e',
            greenLight : 'rgba(34,197,94,.15)',
            orange     : '#f97316',
            indigo     : '#6366f1',
            teal       : '#14b8a6',
            pink       : '#ec4899',
            red        : '#ef4444',
        };

        const statusColors = {
            'wc-completed'  : palette.green,
            'wc-processing' : palette.blue,
            'wc-pending'    : '#eab308',
            'wc-on-hold'    : palette.purple,
            'wc-cancelled'  : palette.red,
            'wc-refunded'   : '#94a3b8',
            'wc-failed'     : '#dc2626',
        };

        const defaults = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleFont: { size: 12 },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 }, color: '#94a3b8', maxTicksLimit: 10 },
                },
                y: {
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { size: 10 }, color: '#94a3b8' },
                    beginAtZero: true,
                },
            },
        };

        const { labels, orders, revenue } = wcdData.chart;
        const currency = wcdData.currency || 'R$';

        // ── Gráfico de pedidos ──
        const ctxOrders = document.getElementById( 'wcd-chart-orders' );
        if ( ctxOrders ) {
            new Chart( ctxOrders, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [ {
                        label: 'Pedidos',
                        data: orders,
                        backgroundColor: palette.purpleLight,
                        borderColor: palette.purple,
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false,
                    } ],
                },
                options: {
                    ...defaults,
                    plugins: {
                        ...defaults.plugins,
                        tooltip: {
                            ...defaults.plugins.tooltip,
                            callbacks: {
                                label: ctx => ` ${ctx.parsed.y} pedido(s)`,
                            },
                        },
                    },
                },
            } );
        }

        // ── Gráfico de receita ──
        const ctxRevenue = document.getElementById( 'wcd-chart-revenue' );
        if ( ctxRevenue ) {
            new Chart( ctxRevenue, {
                type: 'line',
                data: {
                    labels,
                    datasets: [ {
                        label: 'Receita',
                        data: revenue,
                        fill: true,
                        backgroundColor: palette.blueLight,
                        borderColor: palette.blue,
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: palette.blue,
                    } ],
                },
                options: {
                    ...defaults,
                    plugins: {
                        ...defaults.plugins,
                        tooltip: {
                            ...defaults.plugins.tooltip,
                            callbacks: {
                                label: ctx => ` ${currency} ${ctx.parsed.y.toFixed( 2 ).replace( '.', ',' )}`,
                            },
                        },
                    },
                },
            } );
        }

        // ── Donut: pedidos por status ──
        const ctxDonut = document.getElementById( 'wcd-chart-donut' );
        if ( ctxDonut && wcdData.donut && wcdData.donut.length ) {
            const donutLabels  = wcdData.donut.map( d => d.label );
            const donutCounts  = wcdData.donut.map( d => d.count );
            const donutColors  = wcdData.donut.map( d => statusColors[ d.slug ] || palette.purple );

            new Chart( ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: donutLabels,
                    datasets: [ {
                        data: donutCounts,
                        backgroundColor: donutColors,
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6,
                    } ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { font: { size: 10 }, padding: 8, boxWidth: 12, color: '#475569' },
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${ctx.parsed} pedido(s)`,
                            },
                        },
                    },
                },
            } );
        }
    } );
} )();
