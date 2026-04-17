<?php
$title = 'Dashboard';
$pageTitle = 'Dashboard';
?>
<div class="dashboard-page mx-auto w-full max-w-7xl space-y-5 pb-8">
    <!-- Filtros -->
    <div class="flex flex-col gap-3 rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end sm:gap-4">
        <div class="flex flex-col gap-1">
            <label for="dash-period" class="text-xs font-medium text-slate-500">Periodo</label>
            <select id="dash-period" class="min-h-[42px] rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                <option value="7">Ultimos 7 dias</option>
                <option value="30" selected>Ultimos 30 dias</option>
                <option value="90">Ultimos 90 dias</option>
            </select>
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:max-w-xs">
            <label for="dash-pipeline" class="text-xs font-medium text-slate-500">Pipeline</label>
            <select id="dash-pipeline" class="min-h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                <option value="">Todos</option>
            </select>
        </div>
        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:max-w-xs">
            <label for="dash-user" class="text-xs font-medium text-slate-500">Responsavel</label>
            <select id="dash-user" class="min-h-[42px] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                <option value="">Todos</option>
            </select>
        </div>
        <button type="button" id="dash-apply" class="min-h-[42px] rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 sm:self-end">Atualizar</button>
    </div>

    <!-- Linha 1: KPIs principais -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-600 sm:h-14 sm:w-14">
                <svg class="h-6 w-6 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold tabular-nums text-slate-900 sm:text-2xl" id="stat-total-leads">-</div>
                <div class="text-xs text-slate-500 sm:text-sm" id="stat-total-leads-label">Novos leads no periodo</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 sm:h-14 sm:w-14">
                <svg class="h-6 w-6 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold tabular-nums text-slate-900 sm:text-2xl" id="stat-conversion">-</div>
                <div class="text-xs text-slate-500 sm:text-sm">Taxa conversao <span class="text-slate-400">(ganhos / novos)</span></div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 sm:h-14 sm:w-14">
                <svg class="h-6 w-6 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold tabular-nums text-slate-900 sm:text-2xl" id="stat-revenue">-</div>
                <div class="text-xs text-slate-500 sm:text-sm">Valor ganho no periodo</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 sm:h-14 sm:w-14">
                <svg class="h-6 w-6 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-xl font-bold tabular-nums text-slate-900 sm:text-2xl" id="stat-overdue">-</div>
                <div class="text-xs text-slate-500 sm:text-sm">Tarefas atrasadas</div>
            </div>
        </div>
    </div>

    <!-- Linha 2: KPIs secundarios -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div>
                <div class="text-lg font-bold tabular-nums text-slate-900" id="stat-pipeline-open">-</div>
                <div class="text-xs text-slate-500">Valor em pipeline (abertos)</div>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div class="text-lg font-bold tabular-nums text-slate-900" id="stat-active">-</div>
                <div class="text-xs text-slate-500">Leads ativos (filtro)</div>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </div>
            <div>
                <div class="text-lg font-bold tabular-nums text-slate-900" id="stat-wa-unread">-</div>
                <div class="text-xs text-slate-500">Nao lidas WhatsApp <span class="text-slate-400" id="stat-wa-conv-wrap">(<span id="stat-wa-conv">-</span> conversas)</span></div>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-700">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            </div>
            <div>
                <div class="text-lg font-bold tabular-nums text-slate-900" id="stat-unassigned">-</div>
                <div class="text-xs text-slate-500">Sem responsavel</div>
            </div>
        </div>
    </div>

    <!-- Novos leads por dia (barras CSS) -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
            <h3 class="text-sm font-semibold text-slate-900 sm:text-base">Novos leads por dia</h3>
            <p class="text-xs text-slate-500">No periodo e filtros selecionados</p>
        </div>
        <div class="overflow-x-auto p-4 sm:p-5">
            <div id="dash-trend-chart" class="flex min-h-[140px] items-end gap-0.5 sm:gap-1"></div>
            <p id="dash-trend-empty" class="hidden py-8 text-center text-sm text-slate-500">Sem novos leads neste periodo</p>
        </div>
    </div>

    <!-- Temperatura + mensagens WA periodo -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">Temperatura (ativos)</h3>
            <div id="dash-temperature" class="flex flex-col gap-2"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <h3 class="mb-1 text-sm font-semibold text-slate-900">Mensagens WhatsApp no periodo</h3>
            <p class="mb-4 text-xs text-slate-500">Todas as conversas do tenant</p>
            <div class="flex flex-wrap gap-4">
                <div class="rounded-lg bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
                    <div class="text-2xl font-bold tabular-nums text-slate-900" id="stat-msg-in">-</div>
                    <div class="text-xs text-slate-500">Recebidas</div>
                </div>
                <div class="rounded-lg bg-slate-50 px-4 py-3 ring-1 ring-slate-100">
                    <div class="text-2xl font-bold tabular-nums text-slate-900" id="stat-msg-out">-</div>
                    <div class="text-xs text-slate-500">Enviadas</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3 sm:px-5 sm:py-4">
                <h3 class="text-sm font-semibold text-slate-900 sm:text-base">Leads por etapa</h3>
            </div>
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3" id="funnel-chart"></div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3 sm:px-5 sm:py-4">
                <h3 class="text-sm font-semibold text-slate-900 sm:text-base">Atividades recentes</h3>
            </div>
            <div class="max-h-[420px] overflow-y-auto p-4 sm:p-5">
                <div class="space-y-0" id="activity-list"></div>
                <p id="activity-empty" class="hidden py-6 text-center text-sm text-slate-500">Sem atividades recentes</p>
            </div>
        </div>
    </div>
</div>

<?php $scripts = ['dashboard']; ?>
