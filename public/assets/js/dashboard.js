/**
 * Yve CRM - Dashboard JavaScript
 */

const Dashboard = {
    init() {
        this.loadMetrics();
        this.loadActivities();
    },

    async loadMetrics() {
        try {
            const data = await API.dashboard.metrics({ period: 30 });
            
            if (data.success) {
                this.updateStats(data.data);
                this.renderFunnel(data.data.leads_by_stage);
            }
        } catch (error) {
            console.error('Erro ao carregar metricas:', error);
        }
    },

    updateStats(metrics) {
        const totalLeads = document.getElementById('stat-total-leads');
        const conversion = document.getElementById('stat-conversion');
        const revenue = document.getElementById('stat-revenue');
        const overdue = document.getElementById('stat-overdue');

        if (totalLeads) {
            totalLeads.textContent = metrics.total_leads.toLocaleString('es-ES');
        }

        if (conversion) {
            conversion.textContent = metrics.conversion_rate.toFixed(1) + '%';
        }

        if (revenue) {
            revenue.textContent = window.App.formatCurrency(metrics.won_value);
        }

        if (overdue) {
            overdue.textContent = metrics.leads_overdue.toString();
            overdue.classList.toggle('text-red-600', metrics.leads_overdue > 0);
        }
    },

    renderFunnel(stages) {
        const container = document.getElementById('funnel-chart');
        if (!container || !stages || stages.length === 0) {
            if (container) {
                container.innerHTML = '<div class="empty-state-small"><p>Nenhum dado disponivel</p></div>';
            }
            return;
        }

        const maxValue = Math.max(...stages.map(s => s.total || 0));
        const colors = {
            'Pendentes': '#6b7280',
            'Aguardando Resposta': '#f59e0b',
            'HOT': '#ef4444',
            'WARM': '#f97316',
            'COLD': '#3b82f6',
            'Venda Fechada': '#22c55e',
            'Perdido / Win-back': '#8b5cf6'
        };

        const html = stages.map(stage => {
            const percentage = maxValue > 0 ? (stage.total / maxValue) * 100 : 0;
            const color = colors[stage.stage_name] || '#6b7280';

            return `
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                    <div class="w-full shrink-0 text-xs text-slate-600 sm:w-36">${stage.stage_name}</div>
                    <div class="min-h-[36px] flex-1 overflow-hidden rounded-md bg-slate-100">
                        <div class="flex h-9 items-center justify-end rounded-md px-3 text-xs font-semibold text-white" style="width: ${percentage}%; min-width: ${stage.total > 0 ? '2rem' : '0'}; background: ${color};">
                            ${stage.total > 0 ? stage.total : ''}
                        </div>
                    </div>
                    <div class="w-10 shrink-0 text-right text-sm font-semibold text-slate-900">${stage.total || 0}</div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;
    },

    async loadActivities() {
        // Por enquanto, vamos mostrar apenas mensagem vazia
        // Isso sera implementado com eventos reais no futuro
    },

    formatCurrency(value) {
        return window.App.formatCurrency(value);
    }
};

// Inicializar quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.dashboard-page')) {
        Dashboard.init();
    }
});

window.Dashboard = Dashboard;
