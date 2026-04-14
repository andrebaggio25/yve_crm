<?php
$currentPath = App\Core\App::getRequest()->getPath();
$active = fn(string $needle) => strpos($currentPath, $needle) !== false;
$item = fn(bool $on) => 'flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium ' . ($on ? 'text-primary-600' : 'text-slate-500 hover:text-slate-700');
?>
<nav class="fixed bottom-0 left-0 right-0 z-[150] flex border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="Navegacao principal">
    <a href="/dashboard" class="<?= $item($active('dashboard')) ?>">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        Inicio
    </a>
    <a href="/kanban" class="<?= $item($active('kanban')) ?>">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm8 0a1 1 0 011-1h4a1 1 0 011 1v14a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zm8 0a1 1 0 011-1h2a1 1 0 011 1v14a1 1 0 01-1 1h-2a1 1 0 01-1-1V5z"/></svg>
        Leads
    </a>
    <a href="/leads/import" class="<?= $item($active('import')) ?>">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3"/></svg>
        Importar
    </a>
    <a href="/settings/templates" class="<?= $item(strpos($currentPath, 'settings') !== false) ?>">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066"/></svg>
        Config
    </a>
</nav>
