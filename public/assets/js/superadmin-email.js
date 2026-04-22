/**
 * Super Admin — fila e teste de e-mail
 */
function buildQuery() {
    const t = document.getElementById('outbox-filter-tenant')?.value?.trim() ?? '';
    const st = document.getElementById('outbox-filter-status')?.value ?? '';
    const onlySys = document.getElementById('outbox-only-system')?.checked;
    const q = new URLSearchParams();
    q.set('limit', '100');
    if (onlySys) {
        q.set('tenant_id', 'system');
    } else if (t && t.toLowerCase() === 'system') {
        q.set('tenant_id', 'system');
    } else if (t !== '' && /^\d+$/.test(t)) {
        q.set('tenant_id', t);
    }
    if (st) {
        q.set('status', st);
    }
    return q.toString();
}

async function loadOutbox() {
    const el = document.getElementById('sa-outbox');
    if (!el) {
        return;
    }
    try {
        const r = await API.get('/api/superadmin/email-outbox?' + buildQuery());
        const items = r.data?.items || [];
        if (items.length === 0) {
            el.innerHTML = '<p class="text-slate-500">Nenhum registro</p>';
            return;
        }
        const statusClass = (s) => {
            if (s === 'sent') {
                return 'bg-green-100 text-green-800';
            }
            if (s === 'failed') {
                return 'bg-red-100 text-red-800';
            }
            if (s === 'pending') {
                return 'bg-amber-100 text-amber-800';
            }
            return 'bg-slate-100 text-slate-700';
        };
        el.innerHTML = `
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs sm:text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-2 py-2">ID</th>
                        <th class="px-2 py-2">Origem</th>
                        <th class="px-2 py-2">Para</th>
                        <th class="px-2 py-2">Assunto</th>
                        <th class="px-2 py-2">St</th>
                        <th class="px-2 py-2">Tent</th>
                        <th class="px-2 py-2 min-w-[120px]">Erro</th>
                        <th class="px-2 py-2 whitespace-nowrap">Criado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    ${items.map((row) => {
            const org = row.tenant_id == null
                ? '<span class="rounded bg-slate-200 px-1.5 text-slate-800">Sistema</span>'
                : escape((row.tenant_name || '') + ' #' + row.tenant_id);
            const err = row.last_error
                ? `<span class="text-red-600" title="${escapeAttr(row.last_error)}">${escape(row.last_error.slice(0, 80))}${row.last_error.length > 80 ? '...' : ''}</span>`
                : '-';
            return `
                        <tr class="align-top">
                            <td class="px-2 py-2 font-mono">${row.id}</td>
                            <td class="px-2 py-2 max-w-[140px]">${org}</td>
                            <td class="px-2 py-2 break-all">${escape(row.to_email)}</td>
                            <td class="px-2 py-2 max-w-[200px] break-words">${escape((row.subject || '').slice(0, 100))}</td>
                            <td class="px-2 py-2"><span class="rounded-full px-2 py-0.5 text-[10px] sm:text-xs font-medium ${statusClass(row.status)}">${escape(row.status)}</span></td>
                            <td class="px-2 py-2 text-center">${row.attempts}</td>
                            <td class="px-2 py-2 text-[11px]">${err}</td>
                            <td class="px-2 py-2 text-slate-500 whitespace-nowrap">${escape((row.created_at || '').replace('T', ' ').slice(0, 19))}</td>
                        </tr>
                    `;
        }).join('')}
                </tbody>
            </table>
        `;
    } catch (e) {
        el.innerHTML = '<p class="text-red-600">Erro: ' + escape(e.message) + '</p>';
    }
}

function escape(s) {
    if (!s) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadOutbox();
    document.getElementById('outbox-refresh')?.addEventListener('click', loadOutbox);
    document.getElementById('outbox-filter-status')?.addEventListener('change', loadOutbox);
    document.getElementById('outbox-only-system')?.addEventListener('change', () => {
        if (document.getElementById('outbox-only-system')?.checked) {
            const ti = document.getElementById('outbox-filter-tenant');
            if (ti) {
                ti.value = '';
            }
        }
        loadOutbox();
    });
    document.getElementById('outbox-filter-tenant')?.addEventListener('change', () => {
        if (document.getElementById('outbox-filter-tenant')?.value) {
            const c = document.getElementById('outbox-only-system');
            if (c) {
                c.checked = false;
            }
        }
    });

    document.getElementById('form-sa-test')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const to = document.getElementById('sa-test-to')?.value?.trim();
        const msg = document.getElementById('sa-test-msg');
        if (!to) {
            return;
        }
        msg.className = 'mt-2 text-sm text-slate-600';
        msg.textContent = 'Enviando...';
        msg.classList.remove('hidden');
        try {
            const r = await API.post('/api/superadmin/email-test', { to: to });
            msg.className = 'mt-2 text-sm text-green-700';
            msg.textContent = r.message || 'Enviado';
        } catch (err) {
            msg.className = 'mt-2 text-sm text-red-700';
            msg.textContent = err.message || 'Falha';
        }
    });
});
