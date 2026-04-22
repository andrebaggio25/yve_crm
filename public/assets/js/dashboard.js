/**
 * Yve CRM - Dashboard
 */
const Dashboard = {
    escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    },

    getQueryParams() {
        const period = document.getElementById('dash-period')?.value || '30';
        const pipeline = document.getElementById('dash-pipeline')?.value || '';
        const user = document.getElementById('dash-user')?.value || '';
        const q = { period };
        if (pipeline) q.pipeline_id = pipeline;
        if (user) q.user_id = user;
        return q;
    },

    async init() {
        this.bindFilters();
        await this.loadMetrics();
        void this.loadFilterOptions();
    },

    bindFilters() {
        document.getElementById('dash-apply')?.addEventListener('click', () => this.loadMetrics());
        document.getElementById('dash-period')?.addEventListener('change', () => this.loadMetrics());
    },

    async loadFilterOptions() {
        try {
            const [pipesRes, usersRes] = await Promise.all([
                API.get('/api/pipelines'),
                API.dashboard.teamUsers(),
            ]);
            const pipeSel = document.getElementById('dash-pipeline');
            const userSel = document.getElementById('dash-user');
            if (pipeSel && pipesRes.success && pipesRes.data?.pipelines) {
                const cur = pipeSel.value;
                pipeSel.innerHTML = '<option value="">Todos</option>';
                pipesRes.data.pipelines.forEach((p) => {
                    const o = document.createElement('option');
                    o.value = String(p.id);
                    o.textContent = p.name || `Pipeline ${p.id}`;
                    pipeSel.appendChild(o);
                });
                if (cur) pipeSel.value = cur;
            }
            if (userSel && usersRes.success && usersRes.data?.users) {
                const cur = userSel.value;
                userSel.innerHTML = '<option value="">Todos</option>';
                usersRes.data.users.forEach((u) => {
                    const o = document.createElement('option');
                    o.value = String(u.id);
                    o.textContent = u.name || u.email || `User ${u.id}`;
                    userSel.appendChild(o);
                });
                if (cur) userSel.value = cur;
            }
        } catch (e) {
            console.warn('Filtros dashboard:', e);
        }
    },

    showDashboardError(msg) {
        const el = document.getElementById('dashboard-error');
        if (!el) return;
        el.textContent = msg || 'Nao foi possivel carregar as metricas.';
        el.classList.remove('hidden');
    },

    clearDashboardError() {
        document.getElementById('dashboard-error')?.classList.add('hidden');
    },

    async loadMetrics() {
        this.clearDashboardError();
        try {
            const data = await API.dashboard.metrics(this.getQueryParams());
            if (data.success && data.data) {
                this.updateStats(data.data);
                this.renderFunnel(data.data.leads_by_stage);
                this.renderTrend(data.data.leads_by_day);
                this.renderTemperature(data.data.leads_by_temperature);
                this.renderActivities(data.data.recent_events || []);
            } else {
                this.showDashboardError(data.message || 'Resposta invalida do servidor.');
            }
        } catch (error) {
            console.error('Erro ao carregar metricas:', error);
            const m = error?.data?.message || error?.message || 'Erro ao carregar metricas.';
            this.showDashboardError(m);
        }
    },

    updateStats(m) {
        const days = m.period_days || 30;
        const label = document.getElementById('stat-total-leads-label');
        if (label) {
            label.textContent = `Novos leads (${days} dias)`;
        }

        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        set('stat-total-leads', (m.total_leads ?? 0).toLocaleString('pt-BR'));
        set('stat-conversion', `${Number(m.conversion_rate ?? 0).toFixed(1)}%`);
        set('stat-revenue', this.formatCurrency(m.won_value ?? 0));
        set('stat-overdue', String(m.leads_overdue ?? 0));
        const od = document.getElementById('stat-overdue');
        if (od) od.classList.toggle('text-red-600', (m.leads_overdue ?? 0) > 0);

        set('stat-pipeline-open', this.formatCurrency(m.pipeline_open_value ?? 0));
        set('stat-active', (m.active_leads ?? 0).toLocaleString('pt-BR'));
        set('stat-wa-unread', (m.wa_unread_total ?? 0).toLocaleString('pt-BR'));
        set('stat-wa-conv', (m.conversations_open ?? 0).toLocaleString('pt-BR'));
        set('stat-unassigned', (m.unassigned_leads ?? 0).toLocaleString('pt-BR'));

        const un = document.getElementById('stat-unassigned');
        if (un) un.classList.toggle('text-orange-600', (m.unassigned_leads ?? 0) > 0);

        set('stat-msg-in', (m.messages_inbound ?? 0).toLocaleString('pt-BR'));
        set('stat-msg-out', (m.messages_outbound ?? 0).toLocaleString('pt-BR'));
    },

    renderTrend(days) {
        const container = document.getElementById('dash-trend-chart');
        const empty = document.getElementById('dash-trend-empty');
        if (!container) return;
        const list = Array.isArray(days) ? days : [];
        const max = Math.max(...list.map((d) => d.count || 0), 1);
        const total = list.reduce((a, d) => a + (d.count || 0), 0);
        if (total === 0) {
            container.innerHTML = '';
            empty?.classList.remove('hidden');
            return;
        }
        empty?.classList.add('hidden');
        const barW = list.length > 45 ? 'min-w-[3px]' : list.length > 20 ? 'min-w-[5px]' : 'min-w-[8px]';
        const maxPx = 120;
        container.innerHTML = list
            .map((d) => {
                const c = d.count || 0;
                const hPx = max > 0 ? Math.max(c > 0 ? 3 : 0, Math.round((c / max) * maxPx)) : 0;
                const title = `${d.date}: ${c}`;
                return `<div class="flex flex-1 flex-col items-center justify-end ${barW} max-w-[28px] min-w-0 group">
                    <span class="mb-0.5 min-h-[14px] text-[9px] font-medium tabular-nums text-slate-600 sm:text-[10px]">${c > 0 ? c : ''}</span>
                    <div class="w-full rounded-t bg-primary-400/80 transition group-hover:bg-primary-600" style="height:${hPx}px;min-height:${c > 0 ? '3px' : '0'}" title="${this.escapeHtml(title)}"></div>
                    <span class="mt-1 max-w-full truncate text-[8px] leading-none text-slate-400 sm:text-[9px]">${d.date.slice(5)}</span>
                </div>`;
            })
            .join('');
    },

    renderTemperature(temp) {
        const el = document.getElementById('dash-temperature');
        if (!el) return;
        const t = temp || { hot: 0, warm: 0, cold: 0 };
        const rows = [
            { key: 'hot', label: 'Quente', color: 'bg-red-500' },
            { key: 'warm', label: 'Morno', color: 'bg-orange-400' },
            { key: 'cold', label: 'Frio', color: 'bg-sky-500' },
        ];
        const max = Math.max(t.hot || 0, t.warm || 0, t.cold || 0, 1);
        el.innerHTML = rows
            .map((row) => {
                const n = t[row.key] || 0;
                const pct = max > 0 ? (n / max) * 100 : 0;
                return `<div class="flex flex-col gap-1">
                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <span>${row.label}</span>
                        <span class="font-semibold tabular-nums text-slate-900">${n}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full ${row.color}" style="width:${pct}%"></div>
                    </div>
                </div>`;
            })
            .join('');
    },

    eventTypeLabel(type) {
        const map = {
            created: 'Criado',
            updated: 'Atualizado',
            stage_changed: 'Etapa',
            note_added: 'Nota',
            whatsapp_trigger: 'WhatsApp',
            import: 'Importacao',
            email_sent: 'E-mail',
            call_made: 'Ligacao',
            meeting_scheduled: 'Reuniao',
            assigned: 'Responsavel',
            converted: 'Conversao',
            lost: 'Perdido',
            deleted: 'Excluido',
            restored: 'Restaurado',
        };
        return map[type] || type;
    },

    formatRelativeTime(iso) {
        if (!iso) return '';
        const d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        const diff = Date.now() - d.getTime();
        const m = Math.floor(diff / 60000);
        if (m < 1) return 'agora';
        if (m < 60) return `${m} min`;
        const h = Math.floor(m / 60);
        if (h < 24) return `${h} h`;
        const days = Math.floor(h / 24);
        if (days < 7) return `${days} d`;
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    },

    renderActivities(events) {
        const container = document.getElementById('activity-list');
        const empty = document.getElementById('activity-empty');
        if (!container) return;
        if (!events || events.length === 0) {
            container.innerHTML = '';
            empty?.classList.remove('hidden');
            return;
        }
        empty?.classList.add('hidden');
        container.innerHTML = events
            .map((e) => {
                const lead = this.escapeHtml(e.lead_name || 'Lead');
                const desc = this.escapeHtml((e.description || '').slice(0, 120));
                const who = e.user_name ? this.escapeHtml(e.user_name) : '';
                const type = this.eventTypeLabel(e.event_type);
                const when = this.formatRelativeTime(e.created_at);
                const lid = e.lead_id;
                return `<div class="flex gap-3 border-b border-slate-100 py-3 last:border-0">
                    <div class="w-16 shrink-0 text-right text-[10px] font-medium uppercase tracking-wide text-primary-600">${this.escapeHtml(type)}</div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-900">
                            <a href="/kanban" class="font-medium text-primary-700 hover:underline">${lead}</a>
                            ${who ? `<span class="text-slate-400"> · ${who}</span>` : ''}
                        </p>
                        ${desc ? `<p class="mt-0.5 line-clamp-2 text-xs text-slate-600">${desc}</p>` : ''}
                        <p class="mt-1 text-[10px] text-slate-400">${this.escapeHtml(when)}${lid ? ` · #${lid}` : ''}</p>
                    </div>
                </div>`;
            })
            .join('');
    },

    renderFunnel(stages) {
        const container = document.getElementById('funnel-chart');
        if (!container || !stages || stages.length === 0) {
            if (container) {
                container.innerHTML = '<div class="py-4 text-center text-sm text-slate-500">Nenhum dado disponivel</div>';
            }
            return;
        }

        const maxValue = Math.max(...stages.map((s) => s.total || 0));
        const colors = {
            Pendentes: '#6b7280',
            'Aguardando Resposta': '#f59e0b',
            HOT: '#ef4444',
            WARM: '#f97316',
            COLD: '#3b82f6',
            'Venda Fechada': '#22c55e',
            'Perdido / Win-back': '#8b5cf6',
        };

        const html = stages
            .map((stage) => {
                const percentage = maxValue > 0 ? (stage.total / maxValue) * 100 : 0;
                const color = colors[stage.stage_name] || '#6b7280';
                const name = this.escapeHtml(stage.stage_name);
                return `
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                    <div class="w-full shrink-0 text-xs text-slate-600 sm:w-36">${name}</div>
                    <div class="min-h-[36px] flex-1 overflow-hidden rounded-md bg-slate-100">
                        <div class="flex h-9 items-center justify-end rounded-md px-3 text-xs font-semibold text-white" style="width: ${percentage}%; min-width: ${stage.total > 0 ? '2rem' : '0'}; background: ${color};">
                            ${stage.total > 0 ? stage.total : ''}
                        </div>
                    </div>
                    <div class="w-10 shrink-0 text-right text-sm font-semibold tabular-nums text-slate-900">${stage.total || 0}</div>
                </div>`;
            })
            .join('');

        container.innerHTML = html;
    },

    formatCurrency(value) {
        const n = Number(value ?? 0);
        if (typeof window.App !== 'undefined' && typeof window.App.formatCurrency === 'function') {
            return window.App.formatCurrency(n);
        }
        try {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);
        } catch {
            return String(n);
        }
    },
};

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.dashboard-page')) {
        Dashboard.init();
    }
});

window.Dashboard = Dashboard;
