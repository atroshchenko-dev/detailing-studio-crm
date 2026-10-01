document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    let debounceTimer;

    if (searchInput && searchResults) {
        // --- SEARCH HISTORY LOGIC ---
        const getHistory = () => {
            const history = localStorage.getItem('searchHistory');
            return history ? JSON.parse(history) : [];
        };

        const addToHistory = (query) => {
            let history = getHistory();
            // Remove if exists to move to top
            history = history.filter(item => item !== query);
            history.unshift(query);
            // Limit to 5
            if (history.length > 5) history.pop();
            localStorage.setItem('searchHistory', JSON.stringify(history));
        };

        const showHistory = () => {
            const history = getHistory();
            if (history.length === 0) {
                searchResults.style.display = 'none';
                return;
            }

            searchResults.innerHTML = '';
            const header = document.createElement('div');
            header.className = 'list-group-item theme-search text-muted fw-bold small text-uppercase py-1';
            header.style.fontSize = '0.7rem';
            header.innerHTML = '<i class="bi bi-clock-history me-1"></i>Історія пошуку';
            searchResults.appendChild(header);

            history.forEach(term => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action theme-search theme-text theme-border py-2';
                item.innerHTML = `<span class="text-info">${term}</span>`;
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    searchInput.value = term;
                    searchInput.dispatchEvent(new Event('input'));
                });
                searchResults.appendChild(item);
            });
            searchResults.style.display = 'block';
        };

        searchInput.addEventListener('focus', function () {
            if (this.value.trim() === '') {
                showHistory();
            }
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const query = this.value.trim();

            if (query.length < 2) {
                if (query.length === 0) showHistory();
                else searchResults.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                // Add to history on successful search (conceptually, or maybe on click)
                // Actually better to add to history when user CLICKS a result or presses enter.
                // But for now let's just show history on empty focus.

                fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchResults.innerHTML = '';
                        let hasResults = false;

                        // Helper to render category
                        const renderCategory = (items, title) => {
                            if (!items || items.length === 0) return;
                            hasResults = true;

                            const header = document.createElement('div');
                            header.className = 'list-group-item theme-search text-muted fw-bold small text-uppercase py-1';
                            header.style.fontSize = '0.7rem';
                            header.textContent = title;
                            searchResults.appendChild(header);

                            items.forEach(item => {
                                const itemElement = document.createElement('a');
                                itemElement.href = item.url;
                                itemElement.className = 'list-group-item list-group-item-action theme-search theme-text theme-border py-2';
                                itemElement.innerHTML = `
                                    <div class="d-flex align-items-center">
                                        <div class="me-3 text-secondary"><i class="bi ${item.icon}"></i></div>
                                        <div>
                                            <div class="fw-medium">${item.text}</div>
                                            ${item.subtext ? `<small class="text-muted">${item.subtext}</small>` : ''}
                                        </div>
                                    </div>
                                `;

                                // Add to history on click
                                itemElement.addEventListener('click', () => {
                                    addToHistory(query);
                                });

                                searchResults.appendChild(itemElement);
                            });
                        };

                        renderCategory(data.orders, 'Замовлення');
                        renderCategory(data.clients, 'Клієнти');
                        renderCategory(data.cars, 'Авто');
                        renderCategory(data.tasks, 'Задачі');

                        if (hasResults) {
                            searchResults.style.display = 'block';
                        } else {
                            searchResults.innerHTML = '<div class="list-group-item bg-dark text-muted border-secondary">Нічого не знайдено</div>';
                            searchResults.style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                    });
            }, 300);
        });

        // Hide results when clicking outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }

    function getBadgeForType(type) {
        const badges = {
            'order': '<i class="bi bi-receipt me-1"></i>Order',
            'client': '<i class="bi bi-person me-1"></i>Client',
            'task': '<i class="bi bi-check-square me-1"></i>Task',
            'warehouse': '<i class="bi bi-box-seam me-1"></i>Item',
            'service': '<i class="bi bi-tools me-1"></i>Service'
        };
        return badges[type] || type;
    }

    // Sidebar Toggle Logic
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (toggleBtn && sidebar && mainContent) {
        // Load state
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
            toggleBtn.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            toggleBtn.classList.toggle('collapsed');

            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));

            // Trigger window resize to adjust responsive elements like DataTables
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 350); // After transition finishes
        });
    }

    // Mobile Menu Logic
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (mobileToggle && sidebar && overlay) {
        function toggleMobileMenu() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        mobileToggle.addEventListener('click', toggleMobileMenu);
        overlay.addEventListener('click', toggleMobileMenu);

        // Close on route change (delegate to nav links)
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    toggleMobileMenu();
                }
            });
        });
    }


    // Theme Toggle Logic
    const themeToggleBtn = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const htmlElement = document.documentElement;

    if (themeToggleBtn && themeIcon) {
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        if (savedTheme === 'light') {
            htmlElement.setAttribute('data-theme', 'light');
            themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
        }

        themeToggleBtn.addEventListener('click', function () {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';

            htmlElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);

            // Icon update: if new is light, show Moon (to go back to dark)
            // if new is dark, show Sun (to go back to light)
            if (newTheme === 'light') {
                themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
            } else {
                themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
            }

            // Sync Flatpickr theme
            const flatpickrTheme = document.getElementById('flatpickr-theme');
            if (flatpickrTheme) {
                const darkCss = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css';
                const lightCss = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css'; // Standard is light
                flatpickrTheme.href = (newTheme === 'dark') ? darkCss : lightCss;
            }
        });
    }

    // --- Keyboard Shortcuts ---
    document.addEventListener('keydown', function (e) {
        // Don't trigger when typing in inputs
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) {
            // Allow Escape to blur inputs
            if (e.key === 'Escape') {
                e.target.blur();
            }
            return;
        }

        // Ctrl/Cmd + K - Focus global search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('globalSearchInput');
            if (searchInput) searchInput.focus();
        }

        // Ctrl/Cmd + Shift + N - New order (if on orders page and modal exists)
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'N') {
            e.preventDefault();
            const newOrderBtn = document.querySelector('[data-bs-target="#newOrderModal"]');
            if (newOrderBtn) newOrderBtn.click();
        }

        // T - Toggle theme
        if (e.key === 't' || e.key === 'т') { // т for Ukrainian layout
            const themeToggleBtn = document.getElementById('themeToggle');
            if (themeToggleBtn) themeToggleBtn.click();
        }

        // G then O - Go to Orders (Vim-like navigation)
        // G then T - Go to Tasks
        // G then C - Go to Clients
        // G then D - Go to Dashboard
        if (e.key === 'g' || e.key === 'п') {
            window._goPrefix = true;
            setTimeout(() => { window._goPrefix = false; }, 1000);
        }

        if (window._goPrefix) {
            if (e.key === 'o' || e.key === 'щ') { window.location.href = '/orders/'; }
            if (e.key === 't' || e.key === 'е') { window.location.href = '/tasks/'; }
            if (e.key === 'c' || e.key === 'с') { window.location.href = '/clients/'; }
            if (e.key === 'd' || e.key === 'в') { window.location.href = '/'; }
            window._goPrefix = false;
        }

        // ? - Show shortcuts help
        if (e.key === '?') {
            showKeyboardShortcutsHelp();
        }
    });

    // Keyboard shortcuts help modal
    function showKeyboardShortcutsHelp() {
        const shortcuts = [
            { key: 'Ctrl + K', action: 'Глобальний пошук' },
            { key: 'Ctrl + Shift + N', action: 'Нове замовлення' },
            { key: 'T', action: 'Змінити тему' },
            { key: 'G → O', action: 'Перейти до Замовлень' },
            { key: 'G → T', action: 'Перейти до Задач' },
            { key: 'G → C', action: 'Перейти до Клієнтів' },
            { key: 'G → D', action: 'Перейти на Дашборд' },
            { key: 'Esc', action: 'Закрити модальне вікно / вийти з поля' },
            { key: '?', action: 'Показати цю довідку' }
        ];

        let html = '<div class="modal fade" id="keyboardShortcutsModal" tabindex="-1">';
        html += '<div class="modal-dialog"><div class="modal-content">';
        html += '<div class="modal-header theme-border"><h5 class="modal-title theme-text"><i class="bi bi-keyboard me-2"></i>Гарячі клавіші</h5>';
        html += '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        html += '<div class="modal-body"><table class="table theme-text table-sm">';
        html += '<tbody>';
        shortcuts.forEach(s => {
            html += `<tr><td><kbd class="theme-bg theme-text border theme-border">${s.key}</kbd></td><td>${s.action}</td></tr>`;
        });
        html += '</tbody></table></div></div></div></div>';

        // Remove existing modal if any
        const existing = document.getElementById('keyboardShortcutsModal');
        if (existing) existing.remove();

        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('keyboardShortcutsModal'));
        modal.show();
    }
});


// --- DetalStudio Global UI Helpers ---
window.DetalStudio = {
    /**
     * Initialize Flatpickr for Date + Time
     */
    initDateTimePicker: function (selector, options = {}) {
        if (typeof flatpickr === 'undefined') return;
        const defaults = {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            locale: "uk",
            theme: "dark",
            allowInput: true
        };
        return flatpickr(selector, { ...defaults, ...options });
    },

    /**
     * Initialize Flatpickr for Date Only
     */
    initDatePicker: function (selector, options = {}) {
        if (typeof flatpickr === 'undefined') return;
        const defaults = {
            dateFormat: "Y-m-d",
            locale: "uk",
            theme: "dark",
            allowInput: true
        };
        return flatpickr(selector, { ...defaults, ...options });
    },

    /**
     * Show global loading spinner
     */
    showLoading: function () {
        if (document.getElementById('loadingSpinner')) return;
        const spinner = document.createElement('div');
        spinner.className = 'position-fixed top-50 start-50 translate-middle z-index-9999';
        spinner.innerHTML = `
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Завантаження...</span>
            </div>
        `;
        spinner.id = 'loadingSpinner';
        document.body.appendChild(spinner);
    },

    /**
     * Hide global loading spinner
     */
    hideLoading: function () {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.remove();
    },

    /**
     * Show Toast notification
     */
    showToast: function (message, type = 'info') {
        let container = document.getElementById('toastWrapper');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastWrapper';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1100';
            document.body.appendChild(container);
        }

        const id = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${id}" class="toast hide ${type} theme-card theme-border" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header theme-border ${type === 'danger' ? 'text-danger' : (type === 'success' ? 'text-success' : 'text-info')} py-2">
                    <i class="bi ${type === 'danger' ? 'bi-exclamation-triangle-fill' : (type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill')} me-2"></i>
                    <strong class="me-auto">Сповіщення</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body theme-text py-3">
                    ${message}
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = document.getElementById(id);
        const bsToast = new bootstrap.Toast(toastEl, { delay: 5000 });
        bsToast.show();

        // Cleanup after hidden
        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    }
};

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Confirm action with modern modal
function showConfirm(options = {}) {
    const defaults = {
        title: 'Підтвердження',
        message: 'Ви впевнені, що хочете виконати цю дію?',
        confirmText: 'Так, продовжити',
        cancelText: 'Скасувати',
        onConfirm: () => { }
    };
    const settings = { ...defaults, ...options };

    const modalEl = document.getElementById('confirmModal');
    const confirmBtn = document.getElementById('confirmButton');

    if (!modalEl || !confirmBtn) {
        console.error('Confirm modal elements not found');
        if (confirm(settings.message)) {
            settings.onConfirm();
        }
        return;
    }

    document.getElementById('confirmTitle').innerText = settings.title;
    document.getElementById('confirmMessage').innerText = settings.message;
    confirmBtn.innerText = settings.confirmText;

    // Remove previous listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

    newConfirmBtn.addEventListener('click', () => {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        settings.onConfirm();
    });

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

// Confirm delete (legacy helper updated to use new modal)
function confirmDelete(message = 'Ви впевнені, що хочете видалити цей запис?', onConfirm = null) {
    if (onConfirm) {
        showConfirm({
            title: 'Видалення',
            message: message,
            confirmText: 'Видалити',
            onConfirm: onConfirm
        });
        return false;
    }
    return confirm(message); // Keep literal confirm if no callback provided for legacy sync usage
}

// Format currency input
function formatCurrencyInput(input) {
    let value = input.value.replace(/[^0-9.]/g, '');
    if (value) {
        input.value = parseFloat(value).toFixed(2);
    }
}

// Show loading state (legacy wrapper)
function showLoading() {
    window.DetalStudio.showLoading();
}

// Hide loading state (legacy wrapper)
function hideLoading() {
    window.DetalStudio.hideLoading();
}

// Auto-resize textarea
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Initialize Global Components (Tooltips, Flatpickr, DataTables)
document.addEventListener('DOMContentLoaded', function () {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Flatpickr
    window.DetalStudio.initDateTimePicker(".datetime-picker");
    window.DetalStudio.initDatePicker(".date-picker");

    // DataTables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/uk.json',
                paginate: {
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>'
                }
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']],
            drawCallback: function () {
                // Remove adding btn classes to allow our custom CSS to rule
                $('.dataTables_filter input').addClass('form-control form-control-sm');
                $('.dataTables_length select').addClass('form-select form-select-sm');
            }
        });
    }
});

// PWA Install Prompt
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Show install banner if not dismissed before
    if (localStorage.getItem('pwaInstallDismissed') !== 'true') {
        showInstallBanner();
    }
});

function showInstallBanner() {
    // Check if already installed (standalone mode)
    if (window.matchMedia('(display-mode: standalone)').matches) {
        return;
    }

    const banner = document.createElement('div');
    banner.id = 'pwaInstallBanner';
    banner.className = 'position-fixed bottom-0 start-0 end-0 p-3 bg-dark border-top border-info shadow-lg';
    banner.style.zIndex = '9999';
    banner.innerHTML = `
        <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-download fs-3 text-info"></i>
                <div>
                    <div class="fw-bold">Встановити DetalStudio</div>
                    <small class="text-muted">Додайте на головний екран для швидкого доступу</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button id="pwaInstallBtn" class="btn btn-info btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Встановити
                </button>
                <button id="pwaInstallDismiss" class="btn btn-outline-secondary btn-sm">
                    Пізніше
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(banner);

    document.getElementById('pwaInstallBtn').addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('[PWA] User choice:', outcome);
            deferredPrompt = null;
        }
        banner.remove();
    });

    document.getElementById('pwaInstallDismiss').addEventListener('click', () => {
        localStorage.setItem('pwaInstallDismissed', 'true');
        banner.remove();
    });
}

// Check if installed
window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed successfully');
    const banner = document.getElementById('pwaInstallBanner');
    if (banner) banner.remove();
});
