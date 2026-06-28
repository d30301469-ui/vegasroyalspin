(function () {
    'use strict';

    var toastDefaults = {
        duration: 3600,
        close: true,
        gravity: 'top',
        position: 'right',
        stopOnFocus: true
    };

    var colors = {
        success: 'linear-gradient(135deg, #10b981, #059669)',
        error: 'linear-gradient(135deg, #ef4444, #dc2626)',
        warning: 'linear-gradient(135deg, #f59e0b, #d97706)',
        info: 'linear-gradient(135deg, #2563eb, #1d4ed8)'
    };
    var openPaletteDialog = null;

    function closest(target, selector) {
        return target && target.nodeType === 1 && typeof target.closest === 'function'
            ? target.closest(selector)
            : null;
    }

    function normalizeType(type) {
        type = String(type || 'info').toLowerCase();
        if (type === 'danger' || type === 'failed' || type === 'failure') return 'error';
        if (type === 'warn') return 'warning';
        if (type === 'ok' || type === 'saved') return 'success';
        return colors[type] ? type : 'info';
    }

    function notify(type, message, options) {
        type = normalizeType(type);
        var text = String(message || '').trim();
        if (!text) return;

        if (typeof window.Toastify === 'function') {
            window.Toastify(Object.assign({}, toastDefaults, options || {}, {
                text: text,
                style: {
                    background: colors[type] || colors.info,
                    borderRadius: '10px',
                    boxShadow: '0 12px 32px rgba(15, 23, 42, 0.22)',
                    font: '600 13px/1.4 Inter, system-ui, sans-serif'
                }
            })).showToast();
            return;
        }

        if (type === 'error') {
            window.setTimeout(function () {
                window.alert(text);
            }, 0);
        }
    }

    window.AdminToast = {
        success: function (message, options) { notify('success', message, options); },
        error: function (message, options) { notify('error', message, options); },
        warning: function (message, options) { notify('warning', message, options); },
        info: function (message, options) { notify('info', message, options); },
        show: function (payload) {
            payload = payload || {};
            notify(payload.type || 'info', payload.message || '', payload.options || {});
        }
    };

    function closeDropdowns(except) {
        document.querySelectorAll('.dd-wrap.is-open').forEach(function (wrap) {
            if (wrap !== except) wrap.classList.remove('is-open');
        });
    }

    function closeDrawer() {
        document.body.classList.remove('has-drawer-open');
    }

    function openDrawer() {
        document.body.classList.add('has-drawer-open');
    }

    function initDrawer() {
        var backdrop = document.querySelector('[data-drawer-backdrop]');
        if (backdrop) {
            backdrop.addEventListener('click', closeDrawer);
        }
    }

    function initDropdowns() {
        document.addEventListener('click', function (event) {
            if (closest(event.target, '[data-dropdown]') || closest(event.target, '.dd-menu')) return;
            closeDropdowns();
        });

        document.addEventListener('keydown', function (event) {
            if ((event.key !== 'Enter' && event.key !== ' ') || !closest(event.target, '[data-dropdown]')) return;
            event.preventDefault();
            closest(event.target, '[data-dropdown]').click();
        });
    }

    function initThemeToggle() {
        var button = document.getElementById('themeToggle');
        if (!button) return;

        function renderIcon() {
            var theme = document.documentElement.getAttribute('data-theme') || 'light';
            button.innerHTML = theme === 'dark'
                ? '<svg viewBox="0 0 24 24"><path d="M12 3v2M12 19v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M3 12h2M19 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/><circle cx="12" cy="12" r="4"/></svg>'
                : '<svg viewBox="0 0 24 24"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3 6.5 6.5 0 0 0 21 12.8z"/></svg>';
        }

        renderIcon();
        window.__adminRenderThemeIcon = renderIcon;
    }

    function initPalette() {
        var paletteItems = Array.isArray(window.__ADMIN_PALETTE_ITEMS__) ? window.__ADMIN_PALETTE_ITEMS__ : [];
        var paletteBackdrop = null;

        function closePalette() {
            document.body.classList.remove('has-palette-open');
            if (paletteBackdrop) paletteBackdrop.remove();
            paletteBackdrop = null;
        }

        function esc(value) {
            return String(value || '').replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
            });
        }

        function renderPalette(query) {
            if (!paletteBackdrop) return;
            var q = String(query || '').toLowerCase();
            var items = paletteItems.filter(function (item) {
                return !q
                    || String(item.label || '').toLowerCase().indexOf(q) !== -1
                    || String(item.section || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 14);
            var results = paletteBackdrop.querySelector('.palette-results');
            results.innerHTML = items.length ? items.map(function (item) {
                return '<a class="palette-result" href="' + esc(item.href) + '">' +
                    '<span class="palette-result-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">' + (item.icon || '') + '</svg></span>' +
                    '<span class="palette-result-label">' + esc(item.label) + '</span>' +
                    '<span class="palette-result-section">' + esc(item.section) + '</span>' +
                    '</a>';
            }).join('') : '<div class="palette-empty">Sonuç bulunamadı</div>';
        }

        function openPalette() {
            if (paletteBackdrop) return;
            paletteBackdrop = document.createElement('div');
            paletteBackdrop.className = 'palette-backdrop';
            paletteBackdrop.innerHTML = '<div class="palette-modal" role="dialog" aria-modal="true" aria-label="Admin search">' +
                '<div class="palette-input-row"><svg viewBox="0 0 24 24" class="palette-icon" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m21 21-4.3-4.3" fill="none" stroke="currentColor" stroke-width="2"/></svg>' +
                '<input class="palette-input" type="text" placeholder="Modül veya özellik ara..." autocomplete="off"><kbd class="palette-esc">esc</kbd></div>' +
                '<div class="palette-results" role="listbox"></div><div class="palette-foot"><span>Admin modülleri</span><span>Enter ile aç</span></div></div>';
            document.body.appendChild(paletteBackdrop);
            document.body.classList.add('has-palette-open');
            var input = paletteBackdrop.querySelector('.palette-input');
            input.addEventListener('input', function () { renderPalette(input.value); });
            paletteBackdrop.addEventListener('click', function (event) {
                if (event.target === paletteBackdrop) closePalette();
            });
            renderPalette('');
            window.setTimeout(function () { input.focus(); }, 0);
        }
        openPaletteDialog = openPalette;

        document.querySelectorAll('[data-admin-palette-open]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                openPalette();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (paletteBackdrop) closePalette();
                closeDrawer();
                closeDropdowns();
            }
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                openPalette();
            }
        });
    }

    function initAdminModals() {
        var activeModal = null;

        function withModalParam(url) {
            try {
                var parsed = new URL(url, window.location.href);
                parsed.searchParams.set('modal', '1');
                return parsed.toString();
            } catch (e) {
                return url + (url.indexOf('?') === -1 ? '?' : '&') + 'modal=1';
            }
        }

        function closeModal() {
            if (activeModal) {
                activeModal.remove();
                activeModal = null;
            }
            document.body.classList.remove('has-admin-modal');
        }

        function openModal(title, html) {
            closeModal();
            activeModal = document.createElement('div');
            activeModal.className = 'admin-modal-backdrop';
            activeModal.innerHTML = '<section class="admin-modal" role="dialog" aria-modal="true">' +
                '<header class="admin-modal-head"><h2></h2><button type="button" class="admin-modal-close" data-admin-modal-close aria-label="Kapat">×</button></header>' +
                '<div class="admin-modal-body"></div></section>';
            activeModal.querySelector('h2').textContent = title || 'İşlem';
            activeModal.querySelector('.admin-modal-body').innerHTML = html;
            document.body.appendChild(activeModal);
            document.body.classList.add('has-admin-modal');
        }

        document.addEventListener('click', function (event) {
            var closeButton = closest(event.target, '[data-admin-modal-close]');
            if (closeButton) {
                event.preventDefault();
                closeModal();
                return;
            }

            if (activeModal && event.target === activeModal) {
                closeModal();
                return;
            }

            var trigger = closest(event.target, '[data-admin-modal-url]');
            if (!trigger) return;
            event.preventDefault();
            var url = trigger.getAttribute('data-admin-modal-url') || trigger.getAttribute('href') || '';
            if (!url) return;
            window.AdminToast.info('Form yükleniyor...');
            fetch(withModalParam(url), {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Modal içeriği alınamadı.');
                    return response.text();
                })
                .then(function (html) {
                    openModal(trigger.getAttribute('data-admin-modal-title') || trigger.getAttribute('aria-label') || 'İşlem', html);
                })
                .catch(function () {
                    window.AdminToast.error('Modal içeriği yüklenemedi.');
                    window.location.href = url;
                });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeModal) {
                closeModal();
            }
        });
    }

    function initHeaderActions() {
        document.addEventListener('click', function (event) {
            var dropdownTrigger = closest(event.target, '[data-dropdown]');
            if (dropdownTrigger) {
                event.preventDefault();
                event.stopImmediatePropagation();
                var wrap = dropdownTrigger.closest('.dd-wrap');
                if (!wrap) return;
                var isOpen = wrap.classList.contains('is-open');
                closeDropdowns(wrap);
                wrap.classList.toggle('is-open', !isOpen);
                return;
            }

            if (closest(event.target, '[data-admin-palette-open]')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                if (typeof openPaletteDialog === 'function') {
                    openPaletteDialog();
                }
                return;
            }

            if (closest(event.target, '[data-drawer-open]')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                openDrawer();
                return;
            }

            if (closest(event.target, '[data-drawer-close]')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                closeDrawer();
                return;
            }

            var themeButton = closest(event.target, '#themeToggle');
            if (themeButton) {
                event.preventDefault();
                event.stopImmediatePropagation();
                var current = document.documentElement.getAttribute('data-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('dash26-theme', next);
                } catch (e) {}
                if (typeof window.__adminRenderThemeIcon === 'function') {
                    window.__adminRenderThemeIcon();
                }
                window.AdminToast.info(next === 'dark' ? 'Koyu tema etkinleştirildi.' : 'Açık tema etkinleştirildi.');
                return;
            }
        }, true);
    }

    function initFlashToast() {
        document.querySelectorAll('[data-admin-toast]').forEach(function (el) {
            var payload = null;
            try {
                payload = JSON.parse(el.textContent || '{}');
            } catch (e) {
                payload = null;
            }
            if (payload) {
                window.AdminToast.show(payload);
            }
            el.remove();
        });
    }

    function initTableFilters() {
        document.querySelectorAll('[data-admin-table-filter]').forEach(function (filter) {
            filter.addEventListener('input', function () {
                var query = String(filter.value || '').toLowerCase();
                document.querySelectorAll('[data-admin-table-row]').forEach(function (row) {
                    row.style.display = row.textContent.toLowerCase().indexOf(query) === -1 ? 'none' : '';
                });
            });
        });
    }

    function initConfirmForms() {
        document.addEventListener('submit', function (event) {
            var form = closest(event.target, '[data-admin-confirm]');
            if (!form) return;
            var message = String(form.getAttribute('data-admin-confirm') || 'Bu işlem onaylansın mı?').trim();
            if (!message) return;
            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                window.AdminToast.info('İşlem iptal edildi.');
            }
        }, true);
    }

    function initDashboardPanels() {
        var panels = document.querySelectorAll('[data-dashboard-panel]');
        if (!panels.length) return;

        var hasChart = typeof window.Chart === 'function';
        var charts = {};
        var colors = ['#3b82f6', '#22c55e', '#f59e0b', '#94a3b8', '#ef4444', '#eab308', '#06b6d4', '#f97316', '#8b5cf6'];

        function formatValue(value, format) {
            var n = Number(value || 0);
            if (format === 'number') {
                return n.toLocaleString('tr-TR', { maximumFractionDigits: 0 });
            }
            if (format === 'percent') {
                return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
            }
            return '₺' + n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function currentDataset(stats) {
            if (!stats || typeof stats !== 'object') return null;
            var active = stats.active_tab || (Array.isArray(stats.tabs) ? stats.tabs[0] : '');
            if (stats.datasets && stats.datasets[active]) {
                return stats.datasets[active];
            }
            return stats;
        }

        function donutData(stats) {
            var dataset = currentDataset(stats);
            if (!dataset || !Array.isArray(dataset.legend)) {
                return { labels: ['Veri yok'], values: [1], colors: ['rgba(148, 163, 184, .35)'] };
            }
            var labels = dataset.legend.map(function (item) { return item.label; });
            var values = dataset.legend.map(function (item) { return Math.max(0, Number(item.value || 0)); });
            var chartColors = dataset.legend.map(function (item) { return item.color || '#3b82f6'; });
            var total = values.reduce(function (sum, value) { return sum + value; }, 0);
            if (total <= 0) {
                values = [1];
                labels = ['Veri yok'];
                chartColors = ['rgba(148, 163, 184, .35)'];
            }
            return { labels: labels, values: values, colors: chartColors };
        }

        function renderDonut(id, key, stats) {
            var canvas = document.getElementById(id);
            if (!canvas || !hasChart) return;
            var data = donutData(stats);
            charts[key] = new window.Chart(canvas, {
                type: 'doughnut',
                data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: data.colors, borderWidth: 0, hoverOffset: 3 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, color: getComputedStyle(document.documentElement).getPropertyValue('--t-muted'), font: { size: 10, weight: '700' } } },
                        tooltip: { callbacks: { label: function (context) { return context.label + ': ' + Number(context.raw || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } } }
                    }
                }
            });
        }

        function updateDonut(key, stats) {
            var chart = charts[key];
            if (!chart) return;
            var data = donutData(stats);
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data.values;
            chart.data.datasets[0].backgroundColor = data.colors;
            chart.update();
        }

        function updateBars(panel, stats) {
            var dataset = currentDataset(stats);
            if (!panel || !dataset) return;
            var labels = Array.isArray(dataset.labels) ? dataset.labels : [];
            var values = Array.isArray(dataset.values) ? dataset.values.map(Number) : [];
            var formats = Array.isArray(dataset.formats) ? dataset.formats : [];
            var max = Math.max.apply(Math, values.concat([1]));
            var bars = panel.querySelector('.bw-bars');
            if (!bars) return;
            bars.querySelectorAll('[data-dashboard-row]').forEach(function (row) { row.remove(); });
            labels.forEach(function (label, index) {
                var value = Number(values[index] || 0);
                var row = document.createElement('div');
                row.className = 'bw-bar-row';
                row.setAttribute('data-dashboard-row', '');

                var labelEl = document.createElement('div');
                labelEl.className = 'bw-bar-label';
                labelEl.textContent = label;

                var track = document.createElement('div');
                track.className = 'bw-bar-track';
                var fill = document.createElement('span');
                fill.className = 'bw-bar-fill';
                fill.style.width = (max > 0 ? Math.max(1, Math.min(100, (value / max) * 100)) : 1) + '%';
                fill.style.background = colors[index % colors.length];
                track.appendChild(fill);

                var valueEl = document.createElement('div');
                valueEl.className = 'bw-bar-value';
                valueEl.textContent = formatValue(value, formats[index] || 'money');

                row.appendChild(labelEl);
                row.appendChild(track);
                row.appendChild(valueEl);
                bars.appendChild(row);
            });
        }

        var data = window.__NH_DASHBOARD_CHARTS__ || {};
        renderDonut('bw-sport-donut', 'sport', data.sport);
        renderDonut('bw-casino-donut', 'casino', data.casino);
        renderDonut('bw-bonus-donut', 'bonus', data.bonus);

        panels.forEach(function (panel) {
            var key = panel.getAttribute('data-dashboard-panel') || '';
            panel.querySelectorAll('[data-dashboard-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var stats = data[key];
                    if (!stats || !stats.datasets) return;
                    var selected = tab.getAttribute('data-dashboard-tab') || '';
                    if (!stats.datasets[selected]) return;
                    stats.active_tab = selected;
                    panel.querySelectorAll('[data-dashboard-tab]').forEach(function (item) {
                        item.classList.toggle('is-active', item === tab);
                    });
                    updateBars(panel, stats);
                    updateDonut(key, stats);
                });
            });

            var expand = panel.querySelector('[data-dashboard-expand]');
            if (expand) {
                expand.addEventListener('click', function () {
                    panel.classList.toggle('is-expanded');
                    expand.classList.toggle('is-active', panel.classList.contains('is-expanded'));
                });
            }

            var refresh = panel.querySelector('[data-dashboard-refresh]');
            if (refresh) {
                refresh.addEventListener('click', function () {
                    refresh.classList.add('is-active');
                    window.AdminToast.info('Dashboard verileri yenileniyor...');
                    window.location.reload();
                });
            }
        });
    }

    function initCompactTables() {
        document.querySelectorAll('[data-admin-compact-table]').forEach(function (table) {
            var rows = Array.prototype.slice.call(table.querySelectorAll('[data-admin-compact-row]'));
            var surface = table.closest('.admin-surface') || document;
            var empty = surface.querySelector('[data-admin-compact-empty]');
            var globalFilter = surface.querySelector('[data-admin-compact-global-filter]');
            var columnFilters = Array.prototype.slice.call(table.querySelectorAll('[data-admin-compact-column-filter]'));
            var checkAll = table.querySelector('[data-admin-compact-check-all]');
            var exportButton = surface.querySelector('[data-admin-compact-export]');

            function normalized(value) {
                return String(value || '').toLocaleLowerCase('tr-TR').trim();
            }

            function cellValue(cell) {
                return cell ? (cell.getAttribute('data-filter-value') || cell.textContent || '') : '';
            }

            function visibleRows() {
                return rows.filter(function (row) {
                    return row.style.display !== 'none';
                });
            }

            function updateCheckAllState() {
                if (!checkAll) return;
                var visible = visibleRows();
                var checked = visible.filter(function (row) {
                    var checkbox = row.querySelector('[data-admin-compact-row-check]');
                    return checkbox && checkbox.checked;
                }).length;
                checkAll.indeterminate = checked > 0 && checked < visible.length;
                checkAll.checked = visible.length > 0 && checked === visible.length;
            }

            function rowText(row) {
                return normalized(Array.prototype.slice.call(row.cells).map(cellValue).join(' '));
            }

            function applyFilters() {
                var globalQuery = normalized(globalFilter ? globalFilter.value : '');
                var activeFilters = columnFilters.map(function (input) {
                    return {
                        index: Number(input.getAttribute('data-admin-compact-column-filter') || 0),
                        value: normalized(input.value)
                    };
                }).filter(function (filter) {
                    return filter.index > 0 && filter.value !== '';
                });
                var visible = 0;
                rows.forEach(function (row) {
                    var matchesGlobal = !globalQuery || rowText(row).indexOf(globalQuery) !== -1;
                    var matchesColumns = activeFilters.every(function (filter) {
                        var cell = row.cells[filter.index];
                        return cell && normalized(cellValue(cell)).indexOf(filter.value) !== -1;
                    });
                    var show = matchesGlobal && matchesColumns;
                    row.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
                updateCheckAllState();
            }

            if (globalFilter) globalFilter.addEventListener('input', applyFilters);
            columnFilters.forEach(function (input) {
                input.addEventListener('input', applyFilters);
            });
            if (checkAll) {
                checkAll.addEventListener('change', function () {
                    visibleRows().forEach(function (row) {
                        var checkbox = row.querySelector('[data-admin-compact-row-check]');
                        if (checkbox) checkbox.checked = checkAll.checked;
                    });
                    updateCheckAllState();
                });
            }
            rows.forEach(function (row) {
                var checkbox = row.querySelector('[data-admin-compact-row-check]');
                if (checkbox) checkbox.addEventListener('change', updateCheckAllState);
            });

            if (exportButton) {
                exportButton.addEventListener('click', function () {
                    var headers = Array.prototype.slice.call(table.querySelectorAll('thead tr:first-child th'))
                        .slice(1)
                        .map(function (cell) { return '"' + String(cell.textContent || '').trim().replace(/"/g, '""') + '"'; });
                    var lines = [headers.join(',')];
                    visibleRows().forEach(function (row) {
                        var cells = Array.prototype.slice.call(row.cells).slice(1).map(function (cell) {
                            var value = cell.getAttribute('data-export-value') || cell.textContent || '';
                            return '"' + String(value).replace(/\s+/g, ' ').trim().replace(/"/g, '""') + '"';
                        });
                        lines.push(cells.join(','));
                    });
                    var blob = new Blob(["\uFEFF" + lines.join("\n")], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = (exportButton.getAttribute('data-export-name') || 'admin-table') + '.csv';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    window.AdminToast.success('Tablo CSV olarak dışa aktarıldı.');
                });
            }

            applyFilters();
        });
    }

    function init() {
        [
            initHeaderActions,
            initDrawer,
            initDropdowns,
            initThemeToggle,
            initPalette,
            initAdminModals,
            initFlashToast,
            initTableFilters,
            initConfirmForms,
            initDashboardPanels,
            initCompactTables
        ].forEach(function (initializer) {
            try {
                initializer();
            } catch (error) {
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('[admin-ui]', error);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
