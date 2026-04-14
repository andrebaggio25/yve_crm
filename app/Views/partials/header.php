<?php
$currentPath = App\Core\App::getRequest()->getPath();
$isKanban = strpos($currentPath, 'kanban') !== false;
?>
<header class="sticky top-0 z-[100] flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 lg:px-6">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button type="button" id="sidebar-toggle" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 md:hidden" aria-label="Abrir menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <h1 class="truncate text-base font-semibold text-slate-900 sm:text-lg"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
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
