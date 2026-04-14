<?php
$user = App\Core\Session::user();
$currentPath = App\Core\App::getRequest()->getPath();
$hasPendingMigrations = App\Core\Migration::hasPending();
$isActive = fn(string $needle) => strpos($currentPath, $needle) !== false;
?>
<aside id="app-sidebar" class="sidebar fixed inset-y-0 left-0 z-[200] flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 md:relative md:translate-x-0">
    <div class="flex h-14 shrink-0 items-center border-b border-slate-100 px-4">
        <div class="flex items-center gap-2">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-indigo-600 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-900">Yve CRM</span>
        </div>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4 text-sm">
        <div>
            <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Principal</div>
            <div class="space-y-1">
                <a href="/dashboard" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('dashboard') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
                <a href="/kanban" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('kanban') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm8 0a1 1 0 011-1h4a1 1 0 011 1v14a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zm8 0a1 1 0 011-1h2a1 1 0 011 1v14a1 1 0 01-1 1h-2a1 1 0 01-1-1V5z"/></svg>
                    Leads / Kanban
                </a>
                <a href="/leads/import" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('import') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Importar
                </a>
            </div>
        </div>
        <div>
            <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Configuracoes</div>
            <div class="space-y-1">
                <a href="/settings/templates" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('templates') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Templates
                </a>
                <a href="/settings/pipelines" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('pipelines') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Pipelines
                </a>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="/settings/users" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('users') && strpos($currentPath, 'settings') !== false ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Usuarios
                </a>
                <a href="/settings/migrations" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('migrations') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    Migrations
                    <?php if ($hasPendingMigrations): ?>
                        <span class="ml-auto rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">!</span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="border-t border-slate-100 p-3">
        <form action="/logout" method="POST" class="hidden" id="logout-form"><?= App\Core\Session::csrfField() ?></form>
        <button type="button" onclick="document.getElementById('logout-form').submit()" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-medium"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></span>
                <span class="text-xs text-slate-500"><?= htmlspecialchars($user['role'] ?? 'user') ?></span>
            </span>
            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
        </button>
    </div>
</aside>
<div id="sidebar-overlay" class="fixed inset-0 z-[190] bg-black/40 opacity-0 pointer-events-none transition-opacity md:hidden" aria-hidden="true"></div>
