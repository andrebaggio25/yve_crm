/**
 * Super Admin - Tenants Management
 */
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.getElementById('tenants-table');
    const stopImpBtn = document.getElementById('btn-stop-imp');
    const newTenantBtn = document.getElementById('btn-new-tenant');
    const modal = document.getElementById('modal-new-tenant');
    const closeModalBtn = document.getElementById('btn-close-modal');
    const cancelTenantBtn = document.getElementById('btn-cancel-tenant');
    const form = document.getElementById('form-new-tenant');
    const totalCount = document.getElementById('total-count');
    const isImpersonating = window.SUPERADMIN_DATA?.impersonating || false;

    // Modal controls
    if (newTenantBtn && modal) {
        newTenantBtn.addEventListener('click', () => {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    }

    function closeModal() {
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            form?.reset();
        }
    }

    closeModalBtn?.addEventListener('click', closeModal);
    cancelTenantBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Form submit
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Criando...';

        const body = {
            company: form.company.value,
            name: form.name.value,
            email: form.email.value,
            password: form.password.value,
            timezone: form.timezone?.value || 'Europe/Madrid',
            default_locale: form.default_locale?.value || 'es',
            currency: (form.currency?.value || 'EUR').toUpperCase()
        };

        try {
            const r = await API.post('/api/superadmin/tenants', body);
            alert('Empresa criada com sucesso! ID: ' + r.data?.tenant_id);
            closeModal();
            loadTenants();
        } catch (err) {
            alert(err.message || 'Erro ao criar empresa');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Criar Empresa';
        }
    });

    if (stopImpBtn) {
        stopImpBtn.classList.toggle('hidden', !isImpersonating);
        stopImpBtn.addEventListener('click', async () => {
            try {
                const r = await API.post('/api/superadmin/stop-impersonate', {});
                if (r.data?.redirect) {
                    window.location = r.data.redirect;
                } else {
                    window.location.reload();
                }
            } catch (err) {
                alert(err.message || 'Erro ao sair da impersonacao');
            }
        });
    }

    async function loadTenants() {
        if (!tableContainer) return;

        try {
            const r = await API.get('/api/superadmin/tenants');
            const rows = r.data?.tenants || [];

            if (totalCount) {
                totalCount.textContent = rows.length;
            }

            if (rows.length === 0) {
                tableContainer.innerHTML = '<div class="p-8 text-center text-slate-500">Nenhum tenant encontrado</div>';
                return;
            }

            const html = `
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">ID</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-700">Nome</th>
                            <th class="px-3 py-2 text-center font-medium text-slate-700">Status</th>
                            <th class="px-3 py-2 text-center font-medium text-slate-700">Users</th>
                            <th class="px-3 py-2 text-center font-medium text-slate-700">Leads</th>
                            <th class="px-3 py-2 text-center font-medium text-slate-700">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        ${rows.map(t => `
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-slate-900">${t.id}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">${escapeHtml(t.name)}</div>
                                    <div class="text-xs text-slate-500">${escapeHtml(t.slug)}</div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium ${getStatusClass(t.status)}">
                                        ${escapeHtml(t.status)}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center text-slate-700">${t.users_count || 0}</td>
                                <td class="px-3 py-2 text-center text-slate-700">${t.leads_count || 0}</td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        ${t.status !== 'active' ? `
                                            <button type="button" class="rounded px-2 py-1 text-xs font-medium text-green-600 hover:bg-green-50" data-act="${t.id}" data-s="active">
                                                Ativar
                                            </button>
                                        ` : ''}
                                        ${t.status !== 'suspended' ? `
                                            <button type="button" class="rounded px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" data-act="${t.id}" data-s="suspended">
                                                Suspender
                                            </button>
                                        ` : ''}
                                        <button type="button" class="rounded px-2 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50" data-imp="${t.id}">
                                            Entrar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;

            tableContainer.innerHTML = html;

            // Bind event listeners
            tableContainer.querySelectorAll('[data-act]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-act');
                    const status = btn.getAttribute('data-s');
                    const action = status === 'active' ? 'ativar' : 'suspender';

                    if (!confirm(`Deseja ${action} este tenant?`)) return;

                    try {
                        await API.post(`/api/superadmin/tenants/${id}/status`, { status });
                        loadTenants();
                    } catch (err) {
                        alert(err.message || 'Erro ao atualizar status');
                    }
                });
            });

            tableContainer.querySelectorAll('[data-imp]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-imp');

                    if (!confirm('Deseja impersonar este tenant? Voce sera redirecionado para o kanban.')) return;

                    try {
                        const r = await API.post(`/api/superadmin/tenants/${id}/impersonate`, {});
                        if (r.data?.redirect) {
                            window.location = r.data.redirect;
                        }
                    } catch (err) {
                        alert(err.message || 'Erro ao impersonar tenant');
                    }
                });
            });

        } catch (err) {
            tableContainer.innerHTML = `<div class="p-8 text-center text-red-600">Erro ao carregar: ${escapeHtml(err.message)}</div>`;
        }
    }

    function getStatusClass(status) {
        const map = {
            'active': 'bg-green-100 text-green-800',
            'trial': 'bg-blue-100 text-blue-800',
            'suspended': 'bg-red-100 text-red-800',
            'cancelled': 'bg-slate-100 text-slate-800'
        };
        return map[status] || 'bg-slate-100 text-slate-800';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    loadTenants();
});
