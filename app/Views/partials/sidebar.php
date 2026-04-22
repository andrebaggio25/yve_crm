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
            <span class="text-sm font-semibold text-slate-900"><?= htmlspecialchars(__('app.name')) ?></span>
        </div>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4 text-sm">
        <div>
            <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars(__('nav.main')) ?></div>
            <div class="space-y-1">
                <a href="/dashboard" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('dashboard') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <?= htmlspecialchars(__('nav.dashboard')) ?>
                </a>
                <a href="/kanban" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('kanban') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm8 0a1 1 0 011-1h4a1 1 0 011 1v14a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zm8 0a1 1 0 011-1h2a1 1 0 011 1v14a1 1 0 01-1 1h-2a1 1 0 01-1-1V5z"/></svg>
                    <?= htmlspecialchars(__('nav.kanban')) ?>
                </a>
                <a href="/leads/import" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('import') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <?= htmlspecialchars(__('nav.import')) ?>
                </a>
                <a href="/inbox" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('inbox') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <?= htmlspecialchars(__('nav.inbox')) ?>
                </a>
            </div>
        </div>
        <?php if ($user && App\Core\Session::hasRole('superadmin')): ?>
        <!-- Super Admin - Configuracoes Globais -->
        <div>
            <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-amber-600"><?= htmlspecialchars(__('nav.super')) ?></div>
            <div class="space-y-1">
                <a href="/superadmin/settings" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= strpos($currentPath, 'superadmin/settings') !== false ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?= htmlspecialchars(__('nav.system_settings')) ?>
                </a>
                <a href="/superadmin/tenants" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= strpos($currentPath, 'superadmin/tenants') !== false ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <?= htmlspecialchars(__('nav.tenants')) ?>
                </a>
                <a href="/superadmin/email" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= strpos($currentPath, 'superadmin/email') !== false ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Fila e-mail
                </a>
                <a href="/superadmin/migrations" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= strpos($currentPath, 'superadmin/migrations') !== false ? 'bg-amber-50 text-amber-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    <?= htmlspecialchars(__('nav.migrations')) ?>
                    <?php if ($hasPendingMigrations): ?>
                        <span class="ml-auto rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">!</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin do Tenant - Configuracoes do Tenant -->
        <div>
            <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-slate-400"><?= htmlspecialchars(__('nav.settings')) ?></div>
            <div class="space-y-1">
                <a href="/settings/templates" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('templates') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <?= htmlspecialchars(__('nav.templates')) ?>
                </a>
                <a href="/settings/pipelines" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('pipelines') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <?= htmlspecialchars(__('nav.pipelines')) ?>
                </a>
                <a href="/settings/whatsapp" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('whatsapp') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    <?= htmlspecialchars(__('nav.whatsapp')) ?>
                </a>
                <a href="/settings/tenant" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= strpos($currentPath, 'settings/tenant') !== false ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <?= htmlspecialchars(__('nav.tenant')) ?>
                </a>
                <a href="/settings/automations" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= strpos($currentPath, 'settings/automations') !== false ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <?= htmlspecialchars(__('nav.automations')) ?>
                </a>
                <?php if ($user && App\Core\Session::hasRole('admin')): ?>
                <a href="/settings/users" class="flex items-center gap-2 rounded-lg px-3 py-2 font-medium <?= $isActive('users') && strpos($currentPath, 'settings') !== false ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <?= htmlspecialchars(__('nav.users')) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="border-t border-slate-100 p-3">
        <a href="/profile" class="mb-1 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 <?= $isActive('profile') ? 'bg-slate-50' : '' ?>">
            <span class="text-sm font-medium"><?= htmlspecialchars(__('nav.profile')) ?></span>
        </a>
        <form action="/logout" method="POST" class="hidden" id="logout-form"><?= App\Core\Session::csrfField() ?></form>
        <button type="button" onclick="document.getElementById('logout-form').submit()" class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?></span>
            <span class="min-w-0 flex-1">
                <span class="block truncate font-medium"><?= htmlspecialchars($user['name'] ?? 'Usuario') ?></span>
                <span class="text-xs text-slate-500"><?= htmlspecialchars($user['role'] ?? 'user') ?></span>
            </span>
            <span class="text-xs text-slate-500"><?= htmlspecialchars(__('header.logout')) ?></span>
        </button>
    </div>
</aside>
<div id="sidebar-overlay" class="fixed inset-0 z-[190] bg-black/40 opacity-0 pointer-events-none transition-opacity md:hidden" aria-hidden="true"></div>
