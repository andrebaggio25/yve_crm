<?php
$currentPath = App\Core\App::getRequest()->getPath();
$isKanban = strpos($currentPath, 'kanban') !== false;

// Busca tenant atual para mostrar no header
$user = App\Core\Session::user();
$tenantName = '';
$currentPipelineName = '';

if ($user && !empty($user['tenant_id'])) {
    $tenant = App\Core\Database::fetch('SELECT name FROM tenants WHERE id = :id', [':id' => $user['tenant_id']]);
    $tenantName = $tenant['name'] ?? '';
}

// Se estiver no kanban, busca nome do pipeline atual
if ($isKanban) {
    $pipelineId = null;
    if (preg_match('#^kanban/(\d+)$#', $currentPath, $matches)) {
        $pipelineId = (int) $matches[1];
    } elseif ($currentPath === 'kanban') {
        // Pipeline padrao
        $default = App\Core\Database::fetch('SELECT id, name FROM pipelines WHERE is_default = 1 LIMIT 1');
        $pipelineId = $default['id'] ?? null;
        $currentPipelineName = $default['name'] ?? '';
    }

    if ($pipelineId && !$currentPipelineName) {
        $pipeline = App\Core\Database::fetch('SELECT name FROM pipelines WHERE id = :id', [':id' => $pipelineId]);
        $currentPipelineName = $pipeline['name'] ?? '';
    }
}
?>
<header class="sticky top-0 z-[100] flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 lg:px-6">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button type="button" id="sidebar-toggle" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 md:hidden" aria-label="Abrir menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <div class="min-w-0">
            <h1 class="truncate text-base font-semibold text-slate-900 sm:text-lg"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            <?php if ($tenantName): ?>
                <div class="flex items-center gap-1 text-xs text-slate-500">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span class="truncate"><?= htmlspecialchars($tenantName) ?></span>
                    <?php if ($isKanban && $currentPipelineName): ?>
                        <span class="text-slate-300">|</span>
                        <span class="truncate font-medium text-primary-600"><?= htmlspecialchars($currentPipelineName) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isKanban): ?>
        <nav class="hidden items-center gap-1 sm:flex">
            <a href="/kanban" class="rounded-md px-3 py-1.5 text-sm font-medium <?= $currentPath === 'kanban' || preg_match('#^kanban/\d+$#', $currentPath) ? 'bg-primary-100 text-primary-800' : 'text-slate-600 hover:bg-slate-100' ?>">Lista</a>
            <a href="/settings/pipelines" class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Pipelines</a>
        </nav>
        <?php endif; ?>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <div class="hidden max-w-xs items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 lg:flex">
            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" id="global-search" placeholder="Pesquisar..." class="w-40 bg-transparent text-sm outline-none placeholder:text-slate-400" disabled title="Em breve" />
        </div>
        <?php if ($isKanban): ?>
        <a href="/leads/import" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3"/></svg>
            <span class="hidden sm:inline">Importar</span>
        </a>
        <button type="button" id="btn-add-lead" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span class="hidden sm:inline">Adicionar lead</span>
        </button>
        <?php endif; ?>
    </div>
</header>
