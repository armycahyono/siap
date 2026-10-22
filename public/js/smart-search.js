/**
 * Live Table Search (Pencarian Pintar Langsung di Tabel)
 * SMK PGRI Kras - Sistem Supervisi Guru & Kurikulum
 *
 * Mengubah input pencarian menjadi live filter:
 * 1. Menampilkan hasil langsung pada baris tabel (tanpa dropdown).
 * 2. Instant client-side filtering (0ms) untuk responsivitas seketika.
 * 3. Debounced AJAX fetch (250ms) untuk mengambil data akurat dari database lintas halaman secara seamless.
 */
(function () {
    'use strict';

    // Inject minimal loading animation style
    function injectStyles() {
        if (document.getElementById('live-search-styles')) return;

        const style = document.createElement('style');
        style.id = 'live-search-styles';
        style.textContent = `
            .live-search-loading {
                opacity: 0.6;
                transition: opacity 0.2s ease;
                pointer-events: none;
            }
            @keyframes liveSearchSpin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .live-search-spinner {
                animation: liveSearchSpin 0.75s linear infinite;
                display: inline-block;
            }
        `;
        document.head.appendChild(style);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function initLiveSearch(input) {
        if (input.dataset.liveSearchInitialized === 'true') return;
        input.dataset.liveSearchInitialized = 'true';

        const form = input.form;
        if (!form) return;

        // Find the table container (card containing .table-responsive or table)
        function findTableContainer() {
            // 1. Next sibling card after form's card
            const formCard = form.closest('.card');
            if (formCard) {
                let sibling = formCard.nextElementSibling;
                while (sibling) {
                    if (sibling.classList.contains('card') && sibling.querySelector('table')) {
                        return sibling;
                    }
                    sibling = sibling.nextElementSibling;
                }
            }

            // 2. Direct table or .table-responsive inside page
            const tableResp = document.querySelector('.table-responsive');
            if (tableResp) {
                return tableResp.closest('.card') || tableResp;
            }

            const table = document.querySelector('table');
            if (table) {
                return table.closest('.card') || table;
            }

            return null;
        }

        let debounceTimer = null;
        let abortController = null;

        // Search icon for spinner toggle
        const inputContainer = input.closest('.input-icon') || input.closest('.input-group') || input.parentElement;
        const iconEl = inputContainer ? inputContainer.querySelector('.ti-search') : null;

        function setSearching(isSearching) {
            if (!iconEl) return;
            if (isSearching) {
                iconEl.className = 'ti ti-loader-2 live-search-spinner text-primary';
            } else {
                iconEl.className = 'ti ti-search text-muted';
            }
        }

        // 1. Instant client-side row filtering (0ms feedback)
        function instantFilterRows(query) {
            const container = findTableContainer();
            if (!container) return;

            const tbody = container.querySelector('table tbody');
            if (!tbody) return;

            const existingEmpty = tbody.querySelector('#smart-search-empty-row');
            if (existingEmpty) {
                existingEmpty.remove();
            }

            const rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => tr.id !== 'smart-search-empty-row');
            if (rows.length === 0) return;

            const trimmed = query.trim().toLowerCase();

            if (!trimmed) {
                rows.forEach(tr => {
                    tr.style.display = '';
                });
                return;
            }

            let visibleCount = 0;
            rows.forEach(tr => {
                const text = tr.innerText.toLowerCase();
                if (text.includes(trimmed)) {
                    tr.style.display = '';
                    visibleCount++;
                } else {
                    tr.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                const emptyRow = document.createElement('tr');
                emptyRow.id = 'smart-search-empty-row';
                emptyRow.innerHTML = `
                    <td colspan="100%" class="text-center py-4 text-muted">
                        <i class="ti ti-search-off fs-1 d-block mb-2 text-secondary opacity-50"></i>
                        Tidak ada data yang cocok dengan pencarian "<strong>${escapeHtml(query)}</strong>"
                    </td>
                `;
                tbody.appendChild(emptyRow);
            }
        }

        // 2. Debounced server-side AJAX fetch (cross-page accurate database search)
        function fetchServerResults() {
            const container = findTableContainer();
            if (!container) return;

            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            setSearching(true);
            container.classList.add('live-search-loading');

            const url = new URL(form.action || window.location.href, window.location.origin);
            const formData = new FormData(form);

            for (const [key, value] of formData.entries()) {
                if (value !== '') {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            }

            // Always reset page to 1 on new search
            url.searchParams.delete('page');

            fetch(url.toString(), {
                signal: abortController.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Network response not ok');
                return res.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Find the new table container in parsed HTML
                let newContainer = null;
                const formCard = form.closest('.card');
                if (formCard) {
                    const formCardIdx = Array.from(document.querySelectorAll('.card')).indexOf(formCard);
                    const docCards = doc.querySelectorAll('.card');
                    if (formCardIdx >= 0 && docCards[formCardIdx + 1] && docCards[formCardIdx + 1].querySelector('table')) {
                        newContainer = docCards[formCardIdx + 1];
                    }
                }

                if (!newContainer) {
                    const newTableResp = doc.querySelector('.table-responsive');
                    if (newTableResp) {
                        newContainer = newTableResp.closest('.card') || newTableResp;
                    }
                }

                if (newContainer && container) {
                    container.innerHTML = newContainer.innerHTML;
                    bindPaginationLinks(container);
                }

                // Update URL in browser address bar without reload
                window.history.replaceState(null, '', url.toString());

                // Update reset button in form if present
                updateResetButton(url);
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    console.warn('Live search fetch error:', err);
                }
            })
            .finally(() => {
                setSearching(false);
                if (container) {
                    container.classList.remove('live-search-loading');
                }
            });
        }

        // Delegate pagination clicks inside table container
        function bindPaginationLinks(container) {
            container.querySelectorAll('.pagination a.page-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    if (this.href && !this.href.includes('#')) {
                        e.preventDefault();
                        container.classList.add('live-search-loading');
                        fetch(this.href, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                        .then(res => res.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newTable = doc.querySelector('.table-responsive')?.closest('.card') || doc.querySelector('.table-responsive');
                            if (newTable) {
                                container.innerHTML = newTable.innerHTML;
                                bindPaginationLinks(container);
                                window.history.replaceState(null, '', link.href);
                            }
                        })
                        .finally(() => {
                            container.classList.remove('live-search-loading');
                        });
                    }
                });
            });
        }

        // Show/hide reset button dynamically based on search value
        function updateResetButton(url) {
            const hasFilter = input.value.trim() !== '';
            const resetBtn = form.querySelector('a[title*="Reset"], a.btn-outline-secondary, a.btn-light, a[href*="tab="]');
            if (resetBtn && resetBtn.getAttribute('title')?.toLowerCase().includes('reset')) {
                resetBtn.style.display = hasFilter ? '' : 'none';
            }
        }

        // On typing: instant filter + debounced fetch
        input.addEventListener('input', function () {
            const query = this.value;

            // 1. Instant client-side response
            instantFilterRows(query);

            // 2. Debounced database fetch (250ms)
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchServerResults();
            }, 250);
        });

        // Prevent full reload on Enter, fetch immediately
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(debounceTimer);
            fetchServerResults();
        });

        // Also live filter on select changes within the same filter form (e.g. category, semester)
        form.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                fetchServerResults();
            });
        });
    }

    // Auto-setup for all search inputs
    function setupLiveSearch() {
        injectStyles();
        const inputs = document.querySelectorAll('input[name="search"], input[type="search"]');
        inputs.forEach(initLiveSearch);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupLiveSearch);
    } else {
        setupLiveSearch();
    }
})();
