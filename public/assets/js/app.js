/**
 * Yve CRM - Core JavaScript
 */

const App = {
    _sidebarOpen: false,

    /** Locale e fuso alinhados à Europa (€, horário de Madrid). */
    locale: 'es-ES',
    timeZone: 'Europe/Madrid',
    currency: 'EUR',

    init() {
        this.initMobileSidebar();
        this.initToasts();
        this.initDropdowns();
        this.initFlashMessages();
    },

    initMobileSidebar() {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const toggle = document.getElementById('sidebar-toggle');

        if (!sidebar) return;

        const isDesktop = () => window.matchMedia('(min-width: 768px)').matches;

        const setOverlay = (open) => {
            if (!overlay) return;
            if (open) {
                overlay.classList.remove('opacity-0', 'pointer-events-none');
                overlay.classList.add('opacity-100');
            } else {
                overlay.classList.add('opacity-0', 'pointer-events-none');
                overlay.classList.remove('opacity-100');
            }
        };

        const close = () => {
            this._sidebarOpen = false;
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            setOverlay(false);
        };

        const open = () => {
            if (isDesktop()) return;
            this._sidebarOpen = true;
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            setOverlay(true);
        };

        const toggleSidebar = () => {
            if (isDesktop()) return;
            if (this._sidebarOpen || sidebar.classList.contains('translate-x-0')) {
                close();
            } else {
                open();
            }
        };

        if (toggle) {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleSidebar();
            });
        }

        overlay?.addEventListener('click', close);

        document.addEventListener('click', (e) => {
            if (!this._sidebarOpen || isDesktop()) return;
            if (sidebar.contains(e.target) || toggle?.contains(e.target)) return;
            close();
        });

        window.addEventListener('resize', () => {
            if (isDesktop()) {
                sidebar.classList.remove('-translate-x-full', 'translate-x-0');
                setOverlay(false);
                this._sidebarOpen = false;
            } else if (!this._sidebarOpen) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
            }
        });
    },

    initToasts() {
        this.toastContainer = document.getElementById('toast-container');
        if (!this.toastContainer) {
            this.toastContainer = document.createElement('div');
            this.toastContainer.id = 'toast-container';
            this.toastContainer.className = 'toast-container';
            document.body.appendChild(this.toastContainer);
        }
    },

    toast(message, type = 'info', title = null) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icons = {
            success: '<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            error: '<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            warning: '<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>',
            info: '<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        };

        toast.innerHTML = `
            <div class="toast-icon shrink-0 text-current opacity-80">${icons[type] || icons.info}</div>
            <div class="toast-content min-w-0 flex-1">
                ${title ? `<div class="toast-title text-sm font-semibold">${title}</div>` : ''}
                <div class="toast-message text-sm text-slate-700">${message}</div>
            </div>
            <button type="button" class="toast-close shrink-0 rounded p-1 text-slate-500 hover:bg-black/5" aria-label="Fechar">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        `;

        toast.querySelector('.toast-close')?.addEventListener('click', () => toast.remove());

        this.toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    },

    initDropdowns() {
        document.addEventListener('click', (e) => {
            const dropdown = e.target.closest('.dropdown');

            if (dropdown) {
                e.stopPropagation();

                document.querySelectorAll('.dropdown.active').forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });

                dropdown.classList.toggle('active');
            } else {
                document.querySelectorAll('.dropdown.active').forEach(d => {
                    d.classList.remove('active');
                });
            }
        });
    },

    initFlashMessages() {
        const flashMessages = window.flashMessages || [];
        flashMessages.forEach(msg => {
            this.toast(msg.message, msg.type, msg.title);
        });
    },

    /**
     * Modal de confirmacao (substitui window.confirm)
     * @returns {Promise<boolean>}
     */
    confirmDialog(options) {
        const {
            title = 'Confirmar',
            message = '',
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            danger = false
        } = typeof options === 'string' ? { message: options } : options;

        return new Promise((resolve) => {
            const root = document.getElementById('confirm-modal-root');
            if (!root) {
                resolve(window.confirm(message));
                return;
            }

            const backdrop = document.createElement('div');
            backdrop.className = 'fixed inset-0 z-[600] flex items-end justify-center bg-black/50 p-4 sm:items-center';
            backdrop.innerHTML = `
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <h3 class="text-lg font-semibold text-slate-900">${title}</h3>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">${message}</p>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="btn-cancel rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">${cancelText}</button>
                        <button type="button" class="btn-ok rounded-lg px-4 py-2 text-sm font-medium text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-primary-600 hover:bg-primary-700'}">${confirmText}</button>
                    </div>
                </div>
            `;

            const cleanup = (result) => {
                backdrop.remove();
                resolve(result);
            };

            backdrop.querySelector('.btn-cancel').addEventListener('click', () => cleanup(false));
            backdrop.querySelector('.btn-ok').addEventListener('click', () => cleanup(true));
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) cleanup(false);
            });

            root.appendChild(backdrop);
        });
    },

    formatDate(dateString, format = 'short') {
        if (!dateString) return '—';

        const date = new Date(dateString);
        const tz = { timeZone: this.timeZone };

        if (format === 'short') {
            return date.toLocaleDateString(this.locale, {
                ...tz,
                day: '2-digit',
                month: '2-digit',
                year: '2-digit'
            });
        }

        if (format === 'long') {
            return date.toLocaleDateString(this.locale, {
                ...tz,
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        return date.toLocaleDateString(this.locale, tz);
    },

    /** Data e hora no fuso Europe/Madrid (ex.: eventos e histórico). */
    formatDateTime(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return String(iso);
        return d.toLocaleString(this.locale, {
            timeZone: this.timeZone,
            dateStyle: 'short',
            timeStyle: 'short'
        });
    },

    formatCurrency(value) {
        if (value === null || value === undefined || value === '') return '—';
        const n = typeof value === 'number' ? value : parseFloat(String(value).replace(/\s/g, '').replace(',', '.'));
        if (Number.isNaN(n)) return '—';

        return new Intl.NumberFormat(this.locale, {
            style: 'currency',
            currency: this.currency
        }).format(n);
    },

    formatPhone(phone) {
        if (!phone) return '-';

        const cleaned = phone.replace(/\D/g, '');

        if (cleaned.length === 11) {
            return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        }

        if (cleaned.length === 10) {
            return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }

        return phone;
    },

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    showLoading(element) {
        element = element || document.body;

        let loader = element.querySelector('.loading-overlay');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'loading-overlay absolute inset-0 z-[50] flex items-center justify-center rounded-[inherit] bg-white/80';
            loader.innerHTML = '<div class="spinner"></div>';
            if (getComputedStyle(element).position === 'static') {
                element.style.position = 'relative';
            }
            element.appendChild(loader);
        }

        loader.classList.remove('hidden');
    },

    hideLoading(element) {
        element = element || document.body;
        const loader = element.querySelector('.loading-overlay');
        if (loader) {
            loader.classList.add('hidden');
        }
    },

    confirm(message, callback) {
        this.confirmDialog({ message }).then((ok) => {
            if (ok) callback();
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

window.App = App;
