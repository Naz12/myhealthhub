/* MyHealthHub – Mia Debug Console  (v2 — millisecond tracing edition)
 *
 * HOW TO USE:
 *   1. Open browser console on any page with Mia
 *   2. Run:  MiaDebug.enable()   then reload the page
 *   3. A floating panel appears bottom-right: full ms-precision pipeline trace
 *   4. Run:  MiaDebug.disable()  then reload to turn off
 *
 * KEYBOARD / CONTROLS:
 *   [Clear]  – wipe logs     [Export CSV] – download session CSV
 *   [Metrics]– toggle metrics summary panel
 *   [✕]      – hide panel (logging continues)
 *   Filter box – filter by task name (TTS, Chat, WS, Recognition, Noise, Turn…)
 */

(function() {
    'use strict';

    if (!window.MiaDebug || !window.MiaDebug.isEnabled()) return;

    // ── Colour palette ─────────────────────────────────────────────────────
    var LEVEL_BG = { info: 'transparent', warn: 'rgba(253,211,77,0.07)', error: 'rgba(248,113,113,0.10)' };
    var LEVEL_DOT= { info: '#4ade80', warn: '#fbbf24', error: '#f87171' };

    var TASK_COLOR = {
        'AudioUtils':  '#67e8f9',
        'VoiceToggle': '#86efac',
        'VoiceState':  '#f9a8d4',
        'Session':     '#a5b4fc',
        'WS':          '#38bdf8',
        'Greeting':    '#86efac',
        'Noise':       '#94a3b8',
        'Recognition': '#fdba74',
        'Audio':       '#c084fc',
        'Realtime':    '#60a5fa',
        'Turn':        '#f472b6',
        'Lookup':      '#34d399',
        'TTS':         '#fde68a',
        'Chat':        '#a78bfa',
        'ChatSend':    '#60a5fa',
        'BargIn':      '#c4b5fd',
        'Echo':        '#fb923c',
        'Perf':        '#e2e8f0',
        'Recovery':    '#fca5a5',
        'Error':       '#f87171',
    };
    function taskColor(t) { return TASK_COLOR[t] || '#e2e8f0'; }

    // ── State ──────────────────────────────────────────────────────────────
    var panel, logList, metricsPanel, filterInput;
    var entries = [];
    var filterText = '';
    var autoScroll = true;
    var metricsVisible = false;
    var metricRows = {};   // name → tr element

    // ── Helpers ────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function fmtDetail(d) {
        if (typeof d === 'object' && d !== null) {
            try { return esc(JSON.stringify(d)); } catch(e) { return '[object]'; }
        }
        return esc(String(d || ''));
    }
    function fmtMs(n)    { return (n == null ? '—' : (n >= 10000 ? (n/1000).toFixed(1)+'s' : n.toFixed(1)+'ms')); }
    function fmtDelta(n) { return (n == null ? '' : (n > 500 ? '<span style="color:#f87171">+'+fmtMs(n)+'</span>' : n > 100 ? '<span style="color:#fbbf24">+'+fmtMs(n)+'</span>' : '<span style="color:#6b7280">+'+fmtMs(n)+'</span>')); }

    function passes(e) {
        if (!filterText) return true;
        var q = filterText.toLowerCase();
        return (e.task||'').toLowerCase().indexOf(q) >= 0 ||
               (e.stage||'').toLowerCase().indexOf(q) >= 0 ||
               (typeof e.detail === 'string' ? e.detail : JSON.stringify(e.detail||'')).toLowerCase().indexOf(q) >= 0;
    }

    // ── Row builder ────────────────────────────────────────────────────────
    function buildRow(e) {
        var row = document.createElement('div');
        row.style.cssText = 'display:grid;grid-template-columns:55px 60px 130px 1fr;gap:0 6px;' +
            'padding:2px 6px;border-bottom:1px solid rgba(255,255,255,0.03);' +
            'font-family:monospace;font-size:10.5px;line-height:1.45;' +
            'background:' + (LEVEL_BG[e.level] || 'transparent') + ';';

        var tc = taskColor(e.task);
        row.innerHTML =
            '<span style="color:rgba(255,255,255,0.35);text-align:right;white-space:nowrap;">' + fmtMs(e.ms) + '</span>' +
            '<span style="text-align:right;white-space:nowrap;">' + fmtDelta(e.delta_ms) + '</span>' +
            '<span style="color:' + tc + ';font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' +
                '<span style="color:' + LEVEL_DOT[e.level] + '">●</span> ' + esc(e.task) +
                '<span style="color:rgba(255,255,255,0.4);font-weight:400;">:' + esc(e.stage) + '</span>' +
            '</span>' +
            '<span style="color:#e2e8f0;word-break:break-all;">' + fmtDetail(e.detail) + '</span>';
        return row;
    }

    // ── Repaint (filtered) ────────────────────────────────────────────────
    function repaint() {
        if (!logList) return;
        logList.innerHTML = '';
        entries.filter(passes).forEach(function(e) { logList.appendChild(buildRow(e)); });
        if (autoScroll) logList.scrollTop = logList.scrollHeight;
    }

    // ── Metrics panel ─────────────────────────────────────────────────────
    function buildMetricsPanel() {
        var div = document.createElement('div');
        div.style.cssText = 'max-height:140px;overflow-y:auto;background:rgba(0,0,0,0.25);' +
            'border-top:1px solid rgba(255,255,255,0.08);padding:4px 8px;';
        var tbl = document.createElement('table');
        tbl.style.cssText = 'width:100%;border-collapse:collapse;font-family:monospace;font-size:10px;color:#e2e8f0;';
        var hdr = document.createElement('tr');
        hdr.innerHTML = '<th style="text-align:left;color:#94a3b8;padding:1px 4px;">Metric</th>' +
            '<th style="text-align:right;color:#94a3b8;padding:1px 4px;">Last</th>' +
            '<th style="text-align:right;color:#94a3b8;padding:1px 4px;">Avg</th>' +
            '<th style="text-align:right;color:#94a3b8;padding:1px 4px;">Min</th>' +
            '<th style="text-align:right;color:#94a3b8;padding:1px 4px;">Max</th>' +
            '<th style="text-align:right;color:#94a3b8;padding:1px 4px;">n</th>';
        tbl.appendChild(hdr);
        div.appendChild(tbl);
        div._tbl = tbl;
        return div;
    }

    function updateMetric(name, m) {
        if (!metricsPanel || !metricsVisible) return;
        var tbl = metricsPanel._tbl;
        if (!tbl) return;
        var tr = metricRows[name];
        if (!tr) {
            tr = document.createElement('tr');
            tbl.appendChild(tr);
            metricRows[name] = tr;
        }
        var avg = m.count ? Math.round(m.sum / m.count * 10) / 10 : 0;
        var u   = m.unit ? m.unit : 'ms';
        tr.innerHTML = '<td style="padding:1px 4px;color:#a5b4fc;">' + esc(name) + '</td>' +
            '<td style="padding:1px 4px;text-align:right;color:#4ade80;">' + (m.last||0).toFixed(1) + u + '</td>' +
            '<td style="padding:1px 4px;text-align:right;">' + avg.toFixed(1) + u + '</td>' +
            '<td style="padding:1px 4px;text-align:right;">' + (m.min===Infinity?'—':m.min.toFixed(1)+u) + '</td>' +
            '<td style="padding:1px 4px;text-align:right;">' + (m.max===-Infinity?'—':m.max.toFixed(1)+u) + '</td>' +
            '<td style="padding:1px 4px;text-align:right;color:#94a3b8;">' + m.count + '</td>';
    }

    // ── Mount ─────────────────────────────────────────────────────────────
    function btn(label, color) {
        var b = document.createElement('button');
        b.textContent = label;
        b.style.cssText = 'background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);' +
            'border-radius:4px;color:' + (color||'#e2e8f0') + ';font-size:10px;padding:2px 8px;cursor:pointer;white-space:nowrap;';
        return b;
    }

    function mount() {
        if (panel) return;

        panel = document.createElement('div');
        panel.id = 'mia-debug-panel';
        panel.style.cssText = [
            'position:fixed;bottom:16px;right:16px;z-index:99999;',
            'width:680px;max-width:96vw;height:380px;',
            'background:rgba(10,12,22,0.97);border:1px solid rgba(255,255,255,0.12);',
            'border-radius:10px;box-shadow:0 12px 48px rgba(0,0,0,0.7);',
            'display:flex;flex-direction:column;overflow:hidden;',
            'font-family:system-ui,-apple-system,sans-serif;resize:both;'
        ].join('');

        // ── Header bar ────────────────────────────────────────────────────
        var hdr = document.createElement('div');
        hdr.style.cssText = 'display:flex;align-items:center;gap:6px;flex-wrap:wrap;' +
            'padding:5px 8px;background:rgba(255,255,255,0.04);' +
            'border-bottom:1px solid rgba(255,255,255,0.08);flex-shrink:0;';

        var title = document.createElement('span');
        title.style.cssText = 'color:#a78bfa;font-weight:700;font-size:11px;flex:1;white-space:nowrap;';
        title.textContent = '⚡ Mia Debug';
        hdr.appendChild(title);

        // Column legend
        var legend = document.createElement('span');
        legend.style.cssText = 'color:rgba(255,255,255,0.3);font-size:9.5px;font-family:monospace;white-space:nowrap;';
        legend.textContent = 'since-start | Δ | TASK:stage | detail';
        hdr.appendChild(legend);

        // Filter
        filterInput = document.createElement('input');
        filterInput.type = 'text';
        filterInput.placeholder = 'filter task…';
        filterInput.style.cssText = 'background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);' +
            'border-radius:4px;color:#e2e8f0;font-size:10px;padding:2px 6px;width:100px;outline:none;';
        filterInput.addEventListener('input', function() { filterText = filterInput.value; repaint(); });
        hdr.appendChild(filterInput);

        var btnM = btn('Metrics', '#34d399');
        btnM.addEventListener('click', function() {
            metricsVisible = !metricsVisible;
            if (metricsPanel) metricsPanel.style.display = metricsVisible ? '' : 'none';
            if (metricsVisible) {
                metricRows = {};
                var m = MiaDebug.getMetrics();
                Object.keys(m).forEach(function(k) { updateMetric(k, m[k]); });
            }
        });
        hdr.appendChild(btnM);

        var btnClr = btn('Clear');
        btnClr.addEventListener('click', function() {
            entries = []; metricRows = {};
            MiaDebug.clear();
            repaint();
            if (metricsPanel) metricsPanel._tbl.innerHTML = '';
        });
        hdr.appendChild(btnClr);

        var btnExp = btn('Export CSV', '#67e8f9');
        btnExp.addEventListener('click', function() { MiaDebug.export(); });
        hdr.appendChild(btnExp);

        var btnAS = btn('⟳', autoScroll ? '#4ade80' : '#6b7280');
        btnAS.title = 'Toggle auto-scroll';
        btnAS.addEventListener('click', function() {
            autoScroll = !autoScroll;
            btnAS.style.color = autoScroll ? '#4ade80' : '#6b7280';
        });
        hdr.appendChild(btnAS);

        var btnClose = btn('✕');
        btnClose.style.color = 'rgba(255,255,255,0.4)';
        btnClose.addEventListener('click', function() { panel.style.display = 'none'; });
        hdr.appendChild(btnClose);

        panel.appendChild(hdr);

        // ── Column header row ─────────────────────────────────────────────
        var colHdr = document.createElement('div');
        colHdr.style.cssText = 'display:grid;grid-template-columns:55px 60px 130px 1fr;gap:0 6px;' +
            'padding:2px 6px;background:rgba(255,255,255,0.04);border-bottom:1px solid rgba(255,255,255,0.1);' +
            'font-family:monospace;font-size:9px;color:rgba(255,255,255,0.35);flex-shrink:0;';
        colHdr.innerHTML = '<span style="text-align:right">t (ms)</span><span style="text-align:right">Δ (ms)</span><span>TASK:stage</span><span>detail</span>';
        panel.appendChild(colHdr);

        // ── Log list ──────────────────────────────────────────────────────
        logList = document.createElement('div');
        logList.style.cssText = 'flex:1;overflow-y:auto;padding:2px 0;';
        panel.appendChild(logList);

        // ── Metrics panel (hidden by default) ────────────────────────────
        metricsPanel = buildMetricsPanel();
        metricsPanel.style.display = 'none';
        panel.appendChild(metricsPanel);

        // ── Status bar ────────────────────────────────────────────────────
        var statusBar = document.createElement('div');
        statusBar.style.cssText = 'padding:2px 10px;font-size:9.5px;color:rgba(255,255,255,0.25);' +
            'border-top:1px solid rgba(255,255,255,0.05);flex-shrink:0;white-space:nowrap;overflow:hidden;';
        statusBar.textContent = 'MiaDebug active — MiaDebug.disable() + reload to turn off | MiaDebug.export() to download CSV';
        panel.appendChild(statusBar);

        document.body.appendChild(panel);

        // Load already-captured logs
        entries = MiaDebug.getLogs();
        repaint();

        // Populate metrics if already collected
        if (metricsVisible) {
            var m0 = MiaDebug.getMetrics();
            Object.keys(m0).forEach(function(k) { updateMetric(k, m0[k]); });
        }
    }

    // ── Public push interface ─────────────────────────────────────────────
    window.MiaDebugPanel = {
        push: function(entry) {
            entries.push(entry);
            if (logList && passes(entry)) {
                logList.appendChild(buildRow(entry));
                if (autoScroll) logList.scrollTop = logList.scrollHeight;
            }
        },
        metric: function(name, m) {
            updateMetric(name, m);
        }
    };

    // ── Mount after DOM ready ─────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount);
    } else {
        mount();
    }

})();
