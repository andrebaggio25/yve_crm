/**
 * Automations List Page
 * Lista visual de automacoes com cards
 */

const Automations = {
    async init() {
        await this.loadAutomations();
    },

    async loadAutomations() {
        const listEl = document.getElementById('automations-list');
        const emptyEl = document.getElementById('empty-state');
        const loadingEl = document.getElementById('loading-state');

        const triggerLabels = {
            lead_created: 'Lead criado',
            lead_stage_changed: 'Mudanca de etapa',
            whatsapp_message_received: 'Mensagem WhatsApp',
            tag_added: 'Tag adicionada',
        };

        try {
            const r = await API.get('/api/automations');
            const rules = r.data?.rules || [];

            loadingEl.classList.add('hidden');

            if (rules.length === 0) {
                emptyEl.classList.remove('hidden');
                listEl.innerHTML = '';
                return;
            }

            emptyEl.classList.add('hidden');
            listEl.innerHTML = rules.map(rule => {
                const triggerLabel = triggerLabels[rule.trigger_event] || rule.trigger_event;
                const hasFlow = rule.has_flow;
                const statusClass = rule.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600';
                const statusText = rule.is_active ? 'Ativo' : 'Inativo';

                return `
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${statusClass}">
                                    ${statusText}
                                </span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${hasFlow ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600'}">
                                    ${hasFlow ? 'FLOW' : 'LEGACY'}
                                </span>
                            </div>
                            <h3 class="mt-2 text-base font-semibold text-slate-900 truncate">${this.escapeHtml(rule.name)}</h3>
                            <p class="text-sm text-slate-500">${triggerLabel}</p>
                        </div>
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input type="checkbox" class="toggle-automation sr-only peer" data-id="${rule.id}" ${rule.is_active ? 'checked' : ''}>
                            <div class="h-6 w-11 rounded-full bg-slate-200 peer-checked:bg-primary-600 transition-colors"></div>
                            <div class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform ${rule.is_active ? 'translate-x-5' : ''}"></div>
                        </label>
                    </div>

                    ${rule.description ? `<p class="mt-2 text-sm text-slate-600 line-clamp-2">${this.escapeHtml(rule.description)}</p>` : ''}

                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                        <div class="text-xs text-slate-500">
                            ${rule.exec_total > 0 ? `${rule.exec_completed}/${rule.exec_total} execucoes (7d)` : 'Sem execucoes'}
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="view-executions rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" data-id="${rule.id}" data-name="${this.escapeHtml(rule.name)}" title="Execucoes">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </button>
                            <a href="/settings/automations/builder/${rule.id}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <button type="button" class="delete-automation rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-600" data-id="${rule.id}" data-name="${this.escapeHtml(rule.name)}" title="Excluir">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                `;
            }).join('');

            // Bind toggle switches
            document.querySelectorAll('.toggle-automation').forEach(el => {
                el.addEventListener('change', async (e) => {
                    const id = e.target.dataset.id;
                    try {
                        await API.put('/api/automations/' + id + '/toggle');
                        if (window.App && App.toast) {
                            App.toast(e.target.checked ? 'Automacao ativada' : 'Automacao desativada', 'success');
                        }
                    } catch (err) {
                        e.target.checked = !e.target.checked;
                        if (window.App && App.toast) {
                            App.toast('Erro ao alterar status', 'error');
                        }
                    }
                });
            });

            // Bind view executions buttons
            document.querySelectorAll('.view-executions').forEach(el => {
                el.addEventListener('click', (e) => {
                    const btn = e.currentTarget;
                    this.openExecutionsModal(parseInt(btn.dataset.id, 10), btn.dataset.name);
                });
            });

            // Bind delete buttons
            document.querySelectorAll('.delete-automation').forEach(el => {
                el.addEventListener('click', async (e) => {
                    const btn = e.currentTarget;
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    if (!confirm(`Excluir a automacao "${name}"?`)) return;
                    try {
                        await API.delete('/api/automations/' + id);
                        if (window.App && App.toast) {
                            App.toast('Automacao excluida', 'success');
                        }
                        this.loadAutomations();
                    } catch (err) {
                        if (window.App && App.toast) {
                            App.toast('Erro ao excluir', 'error');
                        }
                    }
                });
            });
        } catch (err) {
            loadingEl.classList.add('hidden');
            if (window.App && App.toast) {
                App.toast('Erro ao carregar automacoes', 'error');
            }
            console.error(err);
        }
    },

    async openExecutionsModal(id, name) {
        const existing = document.getElementById('automation-executions-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'automation-executions-modal';
        modal.className = 'fixed inset-0 z-[600] flex items-center justify-center bg-black/50 p-4';
        modal.innerHTML = `
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Execucoes</h3>
                        <p class="text-xs text-slate-500">${this.escapeHtml(name || '')}</p>
                    </div>
                    <button type="button" data-close class="text-slate-400 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div id="exec-modal-body" class="max-h-[70vh] overflow-y-auto px-5 py-4 space-y-3 text-sm text-slate-700">
                    <div class="flex justify-center py-8">
                        <div class="animate-spin h-6 w-6 rounded-full border-2 border-primary-600 border-t-transparent"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.closest('[data-close]')) modal.remove();
        });

        try {
            const r = await API.get(`/api/automations/${id}/logs`);
            const data = r.data || {};
            const executions = data.executions || [];
            const logs = data.logs_legacy || [];
            const body = document.getElementById('exec-modal-body');

            if (!executions.length && !logs.length) {
                body.innerHTML = '<div class="py-6 text-center text-slate-500">Nenhuma execucao registrada ainda.</div>';
                return;
            }

            const statusBadge = (status) => {
                const map = {
                    running: 'bg-amber-100 text-amber-700',
                    completed: 'bg-emerald-100 text-emerald-700',
                    failed: 'bg-rose-100 text-rose-700',
                    waiting: 'bg-slate-100 text-slate-700',
                };
                const cls = map[status] || 'bg-slate-100 text-slate-700';
                return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${cls}">${status || '-'}</span>`;
            };

            const execHtml = executions.map(ex => `
                <details class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <summary class="flex cursor-pointer items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            ${statusBadge(ex.status)}
                            <span class="text-xs text-slate-500">#${ex.id}</span>
                            ${ex.lead_id ? `<a href="/leads?id=${ex.lead_id}" class="text-xs text-primary-600 hover:underline">Lead ${ex.lead_id}</a>` : ''}
                        </div>
                        <span class="text-xs text-slate-500">${ex.started_at || ''}</span>
                    </summary>
                    <div class="mt-2 space-y-1 text-xs text-slate-600">
                        <div><strong>Node atual:</strong> ${ex.current_node_id || '-'}</div>
                        <div><strong>Finalizada:</strong> ${ex.completed_at || '-'}</div>
                        ${ex.error_message ? `<div class="text-rose-600"><strong>Erro:</strong> ${this.escapeHtml(ex.error_message)}</div>` : ''}
                        ${ex.context_json ? `<pre class="mt-2 max-h-40 overflow-auto rounded bg-white p-2 text-[10px] text-slate-600">${this.escapeHtml(ex.context_json)}</pre>` : ''}
                    </div>
                </details>
            `).join('');

            const logsHtml = logs.length ? `
                <div class="mt-4">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Logs legados</div>
                    <ul class="space-y-1 text-xs text-slate-600">
                        ${logs.map(l => `
                            <li class="rounded border border-slate-200 bg-white p-2">
                                <div class="flex justify-between"><span>${l.executed_at || ''}</span><span>${statusBadge(l.status)}</span></div>
                                <div class="text-slate-500">${this.escapeHtml(l.action_type || '')} ${l.lead_id ? `(lead ${l.lead_id})` : ''}</div>
                                ${l.error_message ? `<div class="text-rose-600">${this.escapeHtml(l.error_message)}</div>` : ''}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            ` : '';

            body.innerHTML = (execHtml || '') + logsHtml || '<div class="py-6 text-center text-slate-500">Nenhuma execucao registrada ainda.</div>';
        } catch (err) {
            console.error(err);
            document.getElementById('exec-modal-body').innerHTML =
                '<div class="py-6 text-center text-rose-600">Erro ao carregar execucoes.</div>';
        }
    },

    escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    Automations.init();
});
