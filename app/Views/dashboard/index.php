<?php
$title = 'Dashboard';
$pageTitle = 'Dashboard';
?>
<div class="dashboard-page mx-auto w-full max-w-7xl space-y-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="min-w-0">
                <div class="text-2xl font-bold text-slate-900" id="stat-total-leads">-</div>
                <div class="text-sm text-slate-500">Total de Leads (30 dias)</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-900" id="stat-conversion">-</div>
                <div class="text-sm text-slate-500">Taxa de Conversao</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-900" id="stat-revenue">-</div>
                <div class="text-sm text-slate-500">Vendas Fechadas</div>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-900" id="stat-overdue">-</div>
                <div class="text-sm text-slate-500">Tarefas Atrasadas</div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-semibold text-slate-900">Leads por Etapa</h3>
            </div>
            <div class="p-5">
                <div class="flex flex-col gap-3" id="funnel-chart"></div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-semibold text-slate-900">Atividades Recentes</h3>
            </div>
            <div class="p-5">
                <div class="text-center text-sm text-slate-500" id="activity-list">
                    <p>Sem atividades recentes</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $scripts = ['dashboard']; ?>
