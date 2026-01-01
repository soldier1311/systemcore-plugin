<?php
/**
 * Gold Chart Page
 * Professional Trading View (Frontend)
 */

if (!defined('ABSPATH')) exit;

get_header();
?>

<div class="twentyfourounce-ui gold-chart-page">

    <!-- Page Title -->
    <header class="gold-chart-header">
        <h1>الرسم البياني لأسعار الذهب</h1>
    </header>

    <!-- Tabs -->
    <nav class="gold-chart-tabs">
        <button data-range="day" class="active">يومي</button>
        <button data-range="week">أسبوعي</button>
        <button data-range="month">شهري</button>
        <button data-range="year">سنوي</button>
    </nav>

    <!-- Chart Container -->
    <section class="gold-chart-wrapper">
        <canvas id="goldChart" height="120"></canvas>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const ctx = document.getElementById('goldChart').getContext('2d');
    let chartInstance = null;

    const loadChart = (range = 'day') => {

        const params = new URLSearchParams({
            action: '24ounce_get_chart',
            asset: 'gold_21k',
            range: range
        });

        fetch(twentyfourounceAjax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;

            const labels = res.data.data.map(p => p.time);
            const buy    = res.data.data.map(p => p.buy);
            const sell   = res.data.data.map(p => p.sell);

            if (chartInstance) {
                chartInstance.destroy();
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'BUY',
                            data: buy,
                            borderColor: '#2e7d32',
                            backgroundColor: 'rgba(46,125,50,0.08)',
                            tension: 0.25
                        },
                        {
                            label: 'SELL',
                            data: sell,
                            borderColor: '#b71c1c',
                            backgroundColor: 'rgba(183,28,28,0.08)',
                            tension: 0.25
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': ' + ctx.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxTicksLimit: 10
                            }
                        },
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        });
    };

    // Tabs
    document.querySelectorAll('.gold-chart-tabs button').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.gold-chart-tabs button')
                .forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            loadChart(btn.dataset.range);
        });
    });

    // Initial load
    loadChart('day');

});
</script>

<?php
get_footer();
