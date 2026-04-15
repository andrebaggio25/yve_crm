<?php
$title = 'Automacoes';
$pageTitle = 'Automacoes';
$scripts = ['automations'];
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Automacoes</h1>
            <p class="text-sm text-slate-500 mt-1">Crie fluxos automaticos para gerenciar seus leads</p>
        </div>
        <a href="/settings/automations/builder/0" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Novo Fluxo
        </a>
    </div>

    <!-- Lista de automacoes -->
    <div id="automations-list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Cards serao inseridos aqui via JS -->
    </div>

    <!-- Estado vazio -->
    <div id="empty-state" class="hidden text-center py-12">
        <div class="mx-auto h-12 w-12 text-slate-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>
        <h3 class="mt-4 text-sm font-medium text-slate-900">Nenhuma automacao criada</h3>
        <p class="mt-1 text-sm text-slate-500">Crie seu primeiro fluxo automatizado.</p>
        <a href="/settings/automations/builder/0" class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700">
            Criar fluxo
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
            </svg>
        </a>
    </div>

    <!-- Loading -->
    <div id="loading-state" class="flex justify-center py-12">
        <div class="animate-spin h-8 w-8 rounded-full border-2 border-primary-600 border-t-transparent"></div>
    </div>
</div>
