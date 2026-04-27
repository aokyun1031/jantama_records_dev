(function() {
  var filterForm = document.getElementById('event-filter-form');
  if (filterForm) {
    var submitBtn = filterForm.querySelector('.event-filter-submit');
    var submitCount = filterForm.querySelector('.event-filter-submit-count');
    var clearEl = filterForm.querySelector('.event-filter-clear');
    var checkboxes = filterForm.querySelectorAll('input[name="event_types[]"]');
    var initial = {};
    checkboxes.forEach(function(cb) { initial[cb.value] = cb.checked; });

    function updatePending() {
      var pending = 0;
      checkboxes.forEach(function(cb) {
        if (cb.checked !== initial[cb.value]) pending++;
      });
      filterForm.classList.toggle('has-pending', pending > 0);
      if (submitBtn) {
        submitBtn.hidden = pending === 0;
        submitBtn.setAttribute('data-pending-count', String(pending));
      }
      if (submitCount) {
        submitCount.textContent = pending > 0 ? ' (' + pending + ')' : '';
      }
      if (clearEl) clearEl.hidden = pending > 0;
    }

    checkboxes.forEach(function(cb) {
      cb.addEventListener('change', function() {
        var chip = cb.closest('.event-chip');
        if (chip) chip.classList.toggle('is-selected', cb.checked);
        updatePending();
      });
    });
    updatePending();
  }

  var table = document.querySelector('.h2h-table');
  if (table) {
    var headers = table.querySelectorAll('.h2h-sortable');
    var tbody = table.querySelector('tbody');
    headers.forEach(function(th) {
      th.addEventListener('click', function() {
        var col = parseInt(th.dataset.col);
        var isText = col === 0;
        var currentDir = th.classList.contains('sort-asc') ? 'asc' : th.classList.contains('sort-desc') ? 'desc' : null;
        var newDir = currentDir === 'desc' ? 'asc' : 'desc';
        headers.forEach(function(h) { h.classList.remove('sort-asc', 'sort-desc'); });
        th.classList.add('sort-' + newDir);
        var rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort(function(a, b) {
          var aPlayed = !a.classList.contains('h2h-none');
          var bPlayed = !b.classList.contains('h2h-none');
          if (aPlayed !== bPlayed) return aPlayed ? -1 : 1;
          var aVal = a.children[col].dataset.sortValue;
          var bVal = b.children[col].dataset.sortValue;
          if (isText) {
            return newDir === 'asc' ? aVal.localeCompare(bVal, 'ja') : bVal.localeCompare(aVal, 'ja');
          }
          return newDir === 'asc' ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
        });
        rows.forEach(function(row) { tbody.appendChild(row); });
      });
    });
  }

  // サマリーカードのヒント: モバイル tap で開閉、外側クリックで閉じる
  var hintCards = document.querySelectorAll('.summary-card[data-hint]');
  hintCards.forEach(function(card) {
    card.addEventListener('click', function(e) {
      var wasOpen = card.classList.contains('hint-open');
      hintCards.forEach(function(c) { c.classList.remove('hint-open'); });
      if (!wasOpen) card.classList.add('hint-open');
      e.stopPropagation();
    });
  });
  document.addEventListener('click', function() {
    hintCards.forEach(function(c) { c.classList.remove('hint-open'); });
  });

  function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  }
  function renderCharts() {
    if (typeof Chart === 'undefined') return;
    var data = window.__playerAnalysisData || {};
    var textColor = cssVar('--text') || '#222';
    var subColor = cssVar('--text-sub') || '#666';
    var accentRgb = cssVar('--accent-rgb') || '155,140,232';
    var purpleColor = cssVar('--purple') || '#9b8ce8';
    var coralColor = cssVar('--coral') || '#e8907c';
    var blueColor = cssVar('--blue') || '#7ca8e8';
    var gridColor = 'rgba(128, 128, 128, 0.15)';
    Chart.defaults.color = subColor;
    Chart.defaults.font.family = "'Inter', 'Noto Sans JP', sans-serif";

    // 累計スコア推移折れ線
    var cumEl = document.getElementById('cumulativeChart');
    if (cumEl && data.cumulative) {
      var labels = data.cumulative.map(function(d) { return d.label; });
      var cumValues = data.cumulative.map(function(d) { return d.cumulative; });
      new Chart(cumEl, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: '累計スコア',
            data: cumValues,
            borderColor: purpleColor,
            backgroundColor: 'rgba(' + accentRgb + ', 0.12)',
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.25,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: function(items) { return items[0].label; },
                label: function(ctx) {
                  var s = data.cumulative[ctx.dataIndex];
                  var sign = s.score >= 0 ? '+' : '';
                  return '累計: ' + s.cumulative.toFixed(1) + ' (当回戦 ' + sign + s.score.toFixed(1) + ')';
                }
              }
            }
          },
          scales: {
            x: { ticks: { display: false }, grid: { color: gridColor } },
            y: { grid: { color: gridColor } }
          }
        }
      });
    }

    // 回戦別パフォーマンス（平均スコアの線、点サイズ=試合数、0pt基準線強調）
    var rpEl = document.getElementById('roundPerfChart');
    if (rpEl && data.roundPerf) {
      var rpLabels = data.roundPerf.map(function(d) { return d.round + '回戦'; });
      var rpAvg = data.roundPerf.map(function(d) { return d.avg; });
      var rpGames = data.roundPerf.map(function(d) { return d.games; });
      var maxGames = rpGames.reduce(function(m, g) { return g > m ? g : m; }, 0);
      var pointRadii = rpGames.map(function(g) {
        if (maxGames <= 0) return 4;
        return 4 + (g / maxGames) * 10;
      });
      var pointColors = rpAvg.map(function(v) {
        return v >= 0 ? coralColor : blueColor;
      });
      new Chart(rpEl, {
        type: 'line',
        data: {
          labels: rpLabels,
          datasets: [{
            label: '平均スコア',
            data: rpAvg,
            borderColor: purpleColor,
            backgroundColor: 'rgba(' + accentRgb + ', 0.12)',
            borderWidth: 2.5,
            pointRadius: pointRadii,
            pointHoverRadius: pointRadii.map(function(r) { return r + 2; }),
            pointBackgroundColor: pointColors,
            pointBorderColor: pointColors,
            pointBorderWidth: 0,
            tension: 0.3,
            fill: true,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function(ctx) {
                  var games = rpGames[ctx.dataIndex];
                  var sign = ctx.parsed.y >= 0 ? '+' : '';
                  return '平均 ' + sign + ctx.parsed.y.toFixed(1) + 'pt（' + games + '試合）';
                }
              }
            }
          },
          scales: {
            x: { grid: { color: gridColor } },
            y: {
              title: { display: true, text: '平均スコア (pt)', color: subColor },
              grid: {
                color: function(ctx) {
                  return ctx.tick && ctx.tick.value === 0 ? 'rgba(' + accentRgb + ', 0.5)' : gridColor;
                },
                lineWidth: function(ctx) {
                  return ctx.tick && ctx.tick.value === 0 ? 2 : 1;
                }
              }
            }
          }
        }
      });
    }

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderCharts);
  } else {
    renderCharts();
  }
})();
