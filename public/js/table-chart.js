(function() {
  function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }

  function renderChart() {
    if (typeof Chart === 'undefined') return;
    var data = window.__tableChartData;
    var el = document.getElementById('tableScoreChart');
    if (!el || !data || !data.series) return;

    var subColor = cssVar('--text-sub') || '#666';
    var gridColor = 'rgba(128, 128, 128, 0.15)';
    var colors = [
      cssVar('--purple') || '#9b8ce8',
      cssVar('--coral') || '#e8907c',
      cssVar('--blue') || '#7ca8e8',
      cssVar('--gold') || '#e0b13a',
    ];
    Chart.defaults.color = subColor;
    Chart.defaults.font.family = "'Inter', 'Noto Sans JP', sans-serif";

    var datasets = data.series.map(function(s, i) {
      var color = colors[i % colors.length];
      return {
        label: s.name,
        data: s.cumulative,
        borderColor: color,
        backgroundColor: color,
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 6,
        tension: 0.25,
        fill: false,
      };
    });

    new Chart(el, {
      type: 'line',
      data: { labels: data.labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: true, position: 'bottom' },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                var s = data.series[ctx.datasetIndex];
                var raw = s.raw[ctx.dataIndex];
                var cum = s.cumulative[ctx.dataIndex];
                var sign = raw >= 0 ? '+' : '';
                return s.name + ': 累計 ' + cum.toFixed(1) + '（当局 ' + sign + raw.toFixed(1) + '）';
              }
            }
          }
        },
        scales: {
          x: { grid: { color: gridColor } },
          y: {
            grid: {
              color: function(ctx) { return ctx.tick && ctx.tick.value === 0 ? 'rgba(128,128,128,0.4)' : gridColor; },
              lineWidth: function(ctx) { return ctx.tick && ctx.tick.value === 0 ? 2 : 1; }
            }
          }
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderChart);
  } else {
    renderChart();
  }
})();
